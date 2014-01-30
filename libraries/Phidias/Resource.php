<?php
/*
Resource Controller

Given a resource URI and its attributes (as an array), this class
is responsible for
Executing the resource logic
Finding the proper view (a template with a template engine component to parse it)
returning the rendered view

*/
namespace Phidias;

use Phidias\Resource\Route;

use Phidias\Component\Configuration;
use Phidias\Component\Language;

class Resource
{
	private $URI;
	private $attributes;
    private $acceptedContentTypes;

    private $contentType;


	public function __construct($URI, $attributes = NULL, $acceptedContentTypes = array())
	{
        $this->setURI($URI);
        $this->setAttributes($attributes);
        $this->accept($acceptedContentTypes);
	}

    public function setURI($URI)
    {
        $this->URI = rtrim($URI,'/');
    }

    public function setAttributes($attributes)
    {
        $this->attributes = (array)$attributes;
    }

    public function accept($contentTypes)
    {
        $this->acceptedContentTypes = (array)$contentTypes;
    }



	/* Perform the given request method on the resource */
	public function run($requestMethod)
	{
        $controllers = Route::getControllers($requestMethod, $this->URI);

        if (!count($controllers)) {
            throw new Resource\Exception\NotFound(array('resource' => $this->URI));
        }

        $validController = NULL;
        foreach ($controllers as $possibleController) {
            if (is_callable(array($possibleController[0], $possibleController[1]))) {
                $validController = $possibleController;
                break;
            }
        }

        if ($validController === NULL) {
            throw new Resource\Exception\MethodNotImplemented(array('resource' => $this->URI, 'method' => $requestMethod));
        }


        $controllerClass        = $validController[0];
        $controllerMethod       = $validController[1];
        $controllerArguments    = (isset($validController[1]) && is_array($validController[1])) ? $validController[1] : array();


        /* validate number of arguments */
        $controllerReflection  = new \ReflectionMethod($controllerClass, $controllerMethod);
        $requiredArgumentCount = $controllerReflection->getNumberOfRequiredParameters();

        if (count($controllerArguments) < $requiredArgumentCount) {
            throw new Resource\Exception\WrongArgumentCount(array('expected' => $requiredArgumentCount));
        }



        /* Ready to go! Run controller */
        Debug::startBlock("running controller $controllerClass->$controllerMethod()", 'resource');
        $languagePreviousContext = Language::getCurrentContext();
        Language::useContext(Environment::findModule($controllerReflection->getFileName()));

        ob_start();
        $controllerObject = new $controllerClass($this->attributes);
        $model            = call_user_func_array(array($controllerObject, $controllerMethod), $controllerArguments);
        $stdOut           = ob_get_contents();
        ob_end_clean();

        Language::useContext($languagePreviousContext);
        Debug::endBlock();


        /* Look for the template */
        $modelType = gettype($model);
        if ($modelType === 'object') {
            $modelType = get_class($model);
        }

        $templates = Route::getTemplates($requestMethod, $this->URI, $validController, $modelType);

        /* Use template "model" as last resort */
        $templates[] = "model";


        $view = new View;
        $view->templates($templates);
        $view->acceptTypes($this->acceptedContentTypes);

        $view->set('model', $model);

        if ($viewOutput = $view->render()) {
            $this->contentType = $view->getContentType();
            return $viewOutput;
        }

        Debug::add("no templates found.  Returning stdOut");
        return $stdOut;
	}


    /* Get the content type used to render the latest invocation */
    public function getContentType()
    {
        return $this->contentType;
    }



}