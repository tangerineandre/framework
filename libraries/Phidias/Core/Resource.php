<?php
namespace Phidias\Core;

use Phidias\Component\Language;

class Resource
{
	private $URI;
	private $attributes;
    private $contentType;

	public function __construct($URI, $attributes = NULL)
	{
        $this->URI        = $this->sanitizeURI($URI);
        $this->attributes = $attributes;
	}

	/* Sanitize and set resource's URI  */
	private function sanitizeURI($URI)
	{
		return rtrim($URI,'/');
	}

	/* Perform the given request method on the resource */
	public function run($requestMethod = NULL)
	{
        /* Get associated controller */
        Debug::startBlock("routing '$this->URI'");
        $controllerData = Route::controller($this->URI, $requestMethod);

        /* not found */
        if ($controllerData === NULL) {
            Debug::add("route for '$this->URI' not found");
            throw new Resource\Exception\NotFound(array('resource' => $this->URI));
        }

        $controllerClass      = $controllerData['class'];
        $controllerMethod     = $controllerData['method'];
        $controllerArguments  = $controllerData['arguments'];

        Debug::add("'$this->URI' routed as $controllerClass->$controllerMethod()");
        Debug::endBlock();

        /* validate number of arguments */
        $controllerReflection     = new \ReflectionMethod($controllerClass, $controllerMethod);
        $argumentCount            = $controllerReflection->getNumberOfParameters();
        $requiredArgumentCount    = $controllerReflection->getNumberOfRequiredParameters();
        $incomingArgumentCount    = count($controllerArguments);

        if (($incomingArgumentCount < $requiredArgumentCount) || ($incomingArgumentCount > $argumentCount )) {
            throw new Resource\Exception\WrongArgumentCount(array('expected' => $requiredArgumentCount));
        }

        /* execute callback */
        Debug::startBlock("invoking $controllerClass->$controllerMethod()", 'resource');
        $languagePreviousContext = Language::getCurrentContext();
        Language::useContext(Environment::findModule($controllerReflection->getFileName()));

		ob_start();
		$controllerObject = new $controllerClass($this->attributes);		
        call_user_func_array(array($controllerObject, $controllerMethod), $controllerArguments);
        $output = ob_get_contents();
        ob_end_clean();

        Language::useContext($languagePreviousContext);
        Debug::endBlock();

        /* render view */
        $attributes = $controllerObject->getAttributes();
        $variables  = $controllerObject->getVariables();
        $view       = $controllerObject->getView();

        if ($view !== FALSE) {

            if ($view === NULL) {

                /* When no template is specified, determine the template from the invocable like so:
                 *
                 * Person_Controller::collection()  ->  person/collection
                 * Foo_Man_Controller::main()       ->  foo/man
                 * Foo_Man_Controller::shoo()       ->  foo/man/shoo
                 *
                */

                $basename   = str_replace('_', '/', str_replace('_Controller', '', $controllerClass));
                $view       = strtolower($controllerMethod == 'main' ? $basename : $basename.'/'.$controllerMethod);
            }


            /* 
            At this point we have controller variables (the model) and a view identifier.
            Find a View handler that will render the variables in the given view for the specified mimeType
            */
            $viewData = Route::view($view);

            if ($viewData === NULL) {
                trigger_error("view '$view' not found", E_USER_WARNING);
            } else {

                if ($output) {
                    trigger_error("'$controllerClass->$controllerMethod()' sent output before invoking view: ".$output, E_USER_WARNING);
                }

                $viewClass    = $viewData['class'];
                $templateFile = $viewData['template'];
                $contentType  = $viewData['type'];
                $templateURL  = $viewData['url'];

                Debug::startBlock("rendering view '$templateFile'");

                $viewObject = new $viewClass($templateURL);
                $viewObject->setAttributes($attributes);
                $viewObject->assign($variables);

                $output = $viewObject->fetch($templateFile);

                $this->contentType = $contentType;

                Debug::endBlock();                
            }

        } else if ($variables) {
            trigger_error("Ignoring variables assigned from '$controllerClass->$controllerMethod()'", E_USER_WARNING);
        }

        return $output;
	}

	/* Get the content type used to render the latest invocation */
	public function getContentType()
	{
        return $this->contentType;
	}

}