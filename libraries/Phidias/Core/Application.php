<?php
namespace Phidias\Core;

use \Phidias\Component\View;
use \Phidias\Component\Authorization;
use \Phidias\Component\ExceptionHandler;

class Application
{
    private static $_depth  = -1;
    private static $_stack  = array();
    private static $_layout = FALSE;

    public static function initialize()
    {
        Debug::startBlock('initializing application');

        foreach (Environment::listFileOccurrences(Environment::DIR_CONTROL."/initialize.php") as $initializationFile) {
            Debug::startBlock("including initialization file '$initializationFile'", 'include');
            include $initializationFile;
            Debug::endBlock();
        }

        Debug::endBlock();
    }

    public static function getDepth()
    {
        return self::$_depth;
    }

    public static function getResource($depth = 0)
    {
        return isset(self::$_stack[$depth][0]) ? self::$_stack[$depth][0] : NULL;
    }

    public static function currentResource()
    {
        return self::getResource(0);
    }

    public static function getArguments($depth = 0)
    {
        return isset(self::$_stack[$depth][1]) ? self::$_stack[$depth][1] : NULL;
    }

    private static function sanitize($resource)
    {
        return rtrim($resource,'/');
    }

    public static function run($resource, $requestMethod = NULL, $attributes = NULL)
    {
        $resource = self::sanitize($resource);

        /* Increase depth */
        self::$_depth++;
        if ( self::$_depth > Configuration::get('application.max_depth') ) {
            Debug::add("reached max depth");
            return;
        }

        self::$_stack[self::$_depth] = array($resource, $attributes);

        try {
            $output = self::dispatch($resource, $requestMethod, $attributes);
        } catch (\Exception $e) {
            $output = ExceptionHandler::handle($e);
        }

        if (self::$_depth == 0 && self::$_layout) {
            $layoutFileSource   = NULL;
            $layoutFile         = Route::layout(self::$_layout, $layoutFileSource);

            if ($layoutFile) {
                $languagePreviousSource = Language::getCurrentSource();
                Language::useSource($layoutFileSource);

                Layout::set('output', $output);
                $layout = new Layout($output, Environment::getPublicURL($layoutFileSource));
                $output = $layout->render($layoutFile);

                Language::useSource($languagePreviousSource);
            }
        }

        self::$_depth--;

        return $output;
    }

    private static function dispatch($resource, $requestMethod = NULL, $attributes = NULL)
    {
        /* get associated resource data */
        $invocable = Route::controller($resource, $requestMethod);

        /* not found */
        if ($invocable === NULL) {
            throw new Application\Exception\ResourceNotFound("$resource not found");
        }

        Debug::startBlock("executing resource: $resource", 'resource');

        $class      = $invocable['class'];
        $method     = $invocable['method'];
        $arguments  = $invocable['arguments'];


        /* authorization */
        if (!Authorization::authorized($class, $method, $arguments)) {
            throw new Application\Exception\Unauthorized("access to '$resource' denied");
        }

        /* cache [coming soon] */

        $controllerReflection     = new \ReflectionMethod($class, $method);

        /* validate number of arguments */
        $argumentCount            = $controllerReflection->getNumberOfParameters();
        $requiredArgumentCount    = $controllerReflection->getNumberOfRequiredParameters();
        $incomingArgumentCount    = count($arguments);

        if (($incomingArgumentCount < $requiredArgumentCount) || ($incomingArgumentCount > $argumentCount )) {
            throw new Application\Exception\WrongArgumentCount("$class->$method() expects $requiredArgumentCount arguments");
        }


        /* execute invocable */
        ob_start();

        $languagePreviousSource = Language::getCurrentSource();
        $controllerSource       = Environment::findSource($controllerReflection->getFileName());
        Language::useSource($controllerSource);

        Debug::startBlock("invoking $class->$method()", 'resource');

        $controllerObject = new $class($attributes);
        call_user_func_array( array($controllerObject, $method), $arguments );

        Debug::endBlock();


        $output = ob_get_contents();
        ob_end_clean();

        Language::useSource($languagePreviousSource);


        /* render view */
        $attributes = $controllerObject->getAttributes();
        $variables  = $controllerObject->getVariables();
        $viewData   = $controllerObject->getViewData();
        $template   = $viewData->getTemplate();

        if ($template !== FALSE) {

            if ( $template === NULL ) {

                /* When no template is specified, determine the template from the invocable like so:
                 *
                 * Person_Controller::collection()  ->  person/collection
                 * Foo_Man_Controller::main()       ->  foo/man
                 * Foo_Man_Controller::shoo()       ->  foo/man/shoo
                 *
                */

                $basename = str_replace('_', '/', str_replace('_Controller', '', $class));
                $template = strtolower($method == 'main' ? $basename : $basename.'/'.$method);
            }

            $templateFileSource = NULL;
            $templateFile       = Route::template($template, $templateFileSource);

            if ($templateFile) {

                if ($output) {
                    trigger_error("'$resource' sent output before invoking view: ".$output);
                }

                Debug::startBlock("rendering view '$templateFile'");
                $languagePreviousSource = Language::getCurrentSource();
                Language::useSource($templateFileSource);

                $view = new View(Environment::getPublicURL($templateFileSource));
                $view->setAttributes($attributes);
                $view->assign($variables);
                $output = $view->fetch($templateFile);

                Language::useSource($languagePreviousSource);
                Debug::endBlock();

            } else {
                Debug::add("no template found for $template");
            }


        } else if ($variables) {
            trigger_error("Ignoring variables assigned from '$resource'");
        }

        Debug::endBlock();

        return $output;
    }

    public static function finalize()
    {
        Debug::startBlock('finalizing application');

        foreach ( Environment::listFileOccurrences(Environment::DIR_CONTROL."/finalize.php", FALSE) as $finalizationFile ) {
            Debug::startBlock("loading finalization file '$finalizationFile'", 'include');
            include $finalizationFile;
            Debug::endBlock();
        }

        Debug::endBlock();
    }

    public static function setLayout($layout)
    {
        self::$_layout = $layout;
    }

    public static function getLayout()
    {
        return self::$_layout;
    }
}