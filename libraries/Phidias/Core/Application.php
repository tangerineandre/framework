<?php
namespace Phidias\Core;

use \Phidias\Component\View;
use \Phidias\Component\Authorization;

class Application
{
    private static $depth  = -1;
    private static $stack  = array();
    private static $layout = FALSE;

    public static function getDepth()
    {
        return self::$depth;
    }

    public static function getResource($depth = 0)
    {
        return isset(self::$stack[$depth][0]) ? self::$stack[$depth][0] : NULL;
    }

    public static function currentResource()
    {
        return self::getResource(0);
    }

    public static function getArguments($depth = 0)
    {
        return isset(self::$stack[$depth][1]) ? self::$stack[$depth][1] : NULL;
    }

    private static function sanitize($resource)
    {
        return rtrim($resource,'/');
    }

    public static function run($resource, $requestMethod = NULL, $attributes = NULL)
    {
        $resource = self::sanitize($resource);

        /* Increase depth */
        self::$depth++;
        if ( self::$depth > Configuration::get('application.max_depth') ) {
            Debug::add("reached max depth");
            return;
        }

        self::$stack[self::$depth] = array($resource, $attributes);

        /* Get associated controller */
        $controllerData = Route::controller($resource, $requestMethod);

        /* not found */
        if ($controllerData === NULL) {
            throw new Application\Exception\ResourceNotFound("$resource not found");
        }

        $controllerClass      = $controllerData['class'];
        $controllerMethod     = $controllerData['method'];
        $controllerArguments  = $controllerData['arguments'];

        /* authorization */
        if (!Authorization::authorized($controllerClass, $controllerMethod, $controllerArguments)) {
            throw new Application\Exception\Unauthorized("access to '$resource' denied");
        }

        /* Execute controller */
        $output = self::execute($controllerClass, $controllerMethod, $controllerArguments, $attributes);

        /* Wrap in layout, if applies */
        if (self::$depth == 0 && self::$layout) {
            $layoutFile = Route::layout(self::$layout);

            if ($layoutFile) {
                Layout::set('output', $output);
                $layout = new Layout($output, Environment::getPublicURL(Environment::findModule($layoutFile)));
                $output = $layout->render($layoutFile);
            }
        }

        self::$depth--;

        return $output;
    }

    /*
     * Executes a controller (class, method and arguments)
     * determines and renders the view
     * and returns the output
     */
    private static function execute($controllerClass, $controllerMethod, $controllerArguments = array(), $attributes = NULL)
    {
        /* validate number of arguments */
        $controllerReflection     = new \ReflectionMethod($controllerClass, $controllerMethod);
        $argumentCount            = $controllerReflection->getNumberOfParameters();
        $requiredArgumentCount    = $controllerReflection->getNumberOfRequiredParameters();
        $incomingArgumentCount    = count($controllerArguments);

        if (($incomingArgumentCount < $requiredArgumentCount) || ($incomingArgumentCount > $argumentCount )) {
            throw new Application\Exception\WrongArgumentCount("$controllerClass->$controllerMethod() expects $requiredArgumentCount arguments");
        }

        $languagePreviousContext = Language::getCurrentContext();
        Language::useContext(Environment::findModule($controllerReflection->getFileName()));

        /* execute callback */
        ob_start();

        Debug::startBlock("invoking $controllerClass->$controllerMethod()", 'resource');
        $controllerObject = new $controllerClass($attributes);
        call_user_func_array(array($controllerObject, $controllerMethod), $controllerArguments);
        Debug::endBlock();

        $output = ob_get_contents();
        ob_end_clean();


        /* render view */
        $attributes = $controllerObject->getAttributes();
        $variables  = $controllerObject->getVariables();
        $viewData   = $controllerObject->getViewData();
        $template   = $viewData->getTemplate();

        if ($template !== FALSE) {

            if ($template === NULL) {

                /* When no template is specified, determine the template from the invocable like so:
                 *
                 * Person_Controller::collection()  ->  person/collection
                 * Foo_Man_Controller::main()       ->  foo/man
                 * Foo_Man_Controller::shoo()       ->  foo/man/shoo
                 *
                */

                $basename = str_replace('_', '/', str_replace('_Controller', '', $controllerClass));
                $template = strtolower($controllerMethod == 'main' ? $basename : $basename.'/'.$controllerMethod);
            }

            $templateFile = Route::template($template);

            if ($templateFile) {

                if ($output) {
                    trigger_error("'$controllerClass->$controllerMethod()' sent output before invoking view: ".$output);
                }

                Debug::startBlock("rendering view '$templateFile'");

                $view = new View(Environment::getPublicURL(Environment::findModule($templateFile)));
                $view->setAttributes($attributes);
                $view->assign($variables);
                $output = $view->fetch($templateFile);

                Debug::endBlock();

            } else {
                Debug::add("no template found for $template");
            }


        } else if ($variables) {
            trigger_error("Ignoring variables assigned from '$controllerClass->$controllerMethod()'");
        }

        Language::useContext($languagePreviousContext);

        return $output;
    }

    public static function setLayout($layout)
    {
        self::$layout = $layout;
    }

    public static function getLayout()
    {
        return self::$layout;
    }
}