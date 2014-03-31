<?php
/*
Resource

Given a resource URI and its attributes (as an array), this class
is responsible for
Executing the resource logic
Finding the proper view (a template with a template engine component to parse it)
returning the rendered view

*/
namespace Phidias;

use Phidias\Resource\Route;
use Phidias\Resource\Response;

use Phidias\Component\Authorization;
use Phidias\Component\Language;

class Resource
{
	private $URI;
	private $attributes;
    private $acceptedContentTypes;

	public function __construct($URI, $attributes = array())
	{
        $this->URI        = self::sanitizeURI($URI);
        $this->attributes = (array)$attributes;
	}

    public function accept($acceptedContentTypes)
    {
        $this->acceptedContentTypes = $acceptedContentTypes;
    }


    /* Valid methods */
    public static function getValidMethods()
    {
        return array('options', 'get', 'head', 'post', 'put', 'delete', 'trace', 'connect');
    }

    /* Sanitize the given input method */
    public static function sanitizeMethod($method)
    {
        $method = strtolower(trim($method));
        if (!in_array($method, self::getValidMethods())) {
            return NULL;
        }

        return $method;
    }

    public static function sanitizeURI($URI)
    {
        //Sanitize resource
        $URI = trim($URI, ' /');
        if (empty($URI)) {
            $URI = Configuration::get('phidias.resource.default');
        }        

        return $URI;
    }



    public function execute($method, $data = NULL, $headers = NULL)
    {
        $method = self::sanitizeMethod($method);

        /* Authorize request */
        Debug::startBlock("authorizing '{$this->URI}'");
        if (!Authorization::authorized($method, $this->URI)) {
            throw new Resource\Exception\Unauthorized(array('resource' => $this->URI, 'method' => $method));
        }
        Debug::endBlock();

        /* Find and execute all related controllers */
        $controllers = Route::getControllers($method, $this->URI);

        if (!count($controllers)) {
            throw new Resource\Exception\NotFound(array('resource' => $this->URI));
        }


        $validControllers = array();
        foreach ($controllers as $possibleController) {

            $controllerClass        = $possibleController[0];
            $controllerMethod       = $possibleController[1];
            $controllerArguments    = (isset($possibleController[2]) && is_array($possibleController[2])) ? $possibleController[2] : array();

            if (is_callable(array($controllerClass, $controllerMethod))) {

                /* Controller is callable.  Now check number of arguments */
                $controllerReflection  = new \ReflectionMethod($controllerClass, $controllerMethod);
                $requiredArgumentCount = $controllerReflection->getNumberOfRequiredParameters();
                if (count($controllerArguments) + 1 < $requiredArgumentCount) { //+ 1 because the input is appended to the controller arguments (see below)
                    continue;
                }

                $validControllers[] = $possibleController;
            }
        }

        /* No valid controllers found */
        if (!count($validControllers)) {
            throw new Resource\Exception\MethodNotImplemented(array('resource' => $this->URI, 'method' => $method));
        }

        $request  = new Resource\Request($method, $data, $headers);
        $response = new Resource\Response;
        $stdOut   = '';

        foreach ($validControllers as $validController) {

            $controllerClass        = $validController[0];
            $controllerMethod       = $validController[1];
            $controllerArguments    = (isset($validController[2]) && is_array($validController[2])) ? $validController[2] : array();
            $controllerArguments[]  = $data;

            $allMatchedArguments    = (isset($validController[3]) && is_array($validController[3])) ? $validController[3] : array();


            $controllerObject = new $controllerClass();
            $controllerObject->setAttributes($this->attributes);
            $controllerObject->setArguments($allMatchedArguments);
            $controllerObject->setRequest($request);
            $controllerObject->setResponse($response);


            Debug::startBlock("running controller $controllerClass->$controllerMethod()", 'resource');
            $languagePreviousContext = Language::getCurrentContext();
            Language::useContext(Environment::findModule($controllerReflection->getFileName()));

            ob_start();
            $model  = call_user_func_array(array($controllerObject, $controllerMethod), $controllerArguments);
            $stdOut .= ob_get_contents();
            ob_end_clean();

            Language::useContext($languagePreviousContext);
            Debug::endBlock();

            if ($model !== NULL) {
                $response->data = $model;
            }

        }

        /* Produced output instead of model */
        if ($response->data === NULL && !empty($stdOut)) {
            $response->data = $stdOut;
        }

        if ($this->acceptedContentTypes !== NULL) {

            /* render model in template */
            $modelType = gettype($response->data);
            if ($modelType === 'object') {
                $modelType = get_class($response->data);
            }

            $templates   = Route::getTemplates($method, $this->URI, $modelType);
            $templates[] = "model";

            $view = new View;
            $view->templates($templates);
            $view->acceptTypes($this->acceptedContentTypes);
            $view->set('model', $response->data);

            if ($viewOutput = $view->render()) {
                $response->contentType = $view->getContentType();
                $response->body        = $viewOutput;
            } else {
                $response->contentType = 'text/plain';
                $response->body        = print_r($response->data, true);
            }

        }

        return $response;  
    }



    public function options($data = null)
    {
        return $this->execute('options', $data);
    }

    public function get($data = null)
    {
        return $this->execute('get', $data);
    }

    public function head($data = null)
    {
        return $this->execute('head', $data);
    }

    public function post($data = null)
    {
        return $this->execute('post', $data);
    }

    public function put($data = null)
    {
        return $this->execute('put', $data);
    }

    public function delete($data = null)
    {
        return $this->execute('delete', $data);
    }

    public function trace($data = null)
    {
        return $this->execute('trace', $data);
    }

    public function connect($data = null)
    {
        return $this->execute('connect', $data);
    }


}