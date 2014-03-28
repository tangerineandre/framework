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
use Phidias\Resource\Response;

use Phidias\Component\Authorization;
use Phidias\Component\Language;

class Resource
{
	private $URI;
	private $attributes;
    private $acceptedContentTypes;

	public function __construct($URI)
	{
        $this->URI                  = self::sanitizeURI($URI);
        $this->attributes           = array();
        $this->acceptedContentTypes = array();
	}

    public function setAttributes($attributes)
    {
        $this->attributes = (array)$attributes;
    }

    public function accept($contentTypes)
    {
        $this->acceptedContentTypes = (array)$contentTypes;
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



    public function execute($method, $data = NULL)
    {
        $method = self::sanitizeMethod($method);

        /* Authorize request */
        Debug::startBlock("authorizing '{$this->URI}'");
        if (!Authorization::authorized($method, $this->URI)) {
            throw new Resource\Exception\Unauthorized(array('resource' => $this->URI, 'method' => $method));
        }
        Debug::endBlock();


        /* Authorization OK.  Initialize response */
        $response = new Resource\Response;


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
                if (count($controllerArguments) < $requiredArgumentCount) {
                    continue;
                }

                $validControllers[] = $possibleController;
            }
        }

        /* No valid controllers found */
        if (!count($validControllers)) {
            throw new Resource\Exception\MethodNotImplemented(array('resource' => $this->URI, 'method' => $method));
        }

        $stdOut = '';

        foreach ($validControllers as $validController) {

            $controllerClass        = $validController[0];
            $controllerMethod       = $validController[1];
            $controllerArguments    = (isset($validController[2]) && is_array($validController[2])) ? $validController[2] : array();

            $controllerObject = new $controllerClass($response);
            $controllerObject->setAttributes($this->attributes);
            $controllerObject->setData($data);

            Debug::startBlock("running controller $controllerClass->$controllerMethod()", 'resource');
            $languagePreviousContext = Language::getCurrentContext();
            Language::useContext(Environment::findModule($controllerReflection->getFileName()));

            ob_start();
            $response->model = call_user_func_array(array($controllerObject, $controllerMethod), $controllerArguments);
            $stdOut          .= ob_get_contents();
            ob_end_clean();

            Language::useContext($languagePreviousContext);
            Debug::endBlock();

        }

        /* Produced output instead of model */
        if ($response->model === NULL && !empty($stdOut)) {
            $response->model = $stdOut;
        }


        /* render model in template*/
        $modelType = gettype($response->model);
        if ($modelType === 'object') {
            $modelType = get_class($response->model);
        }

        $templates   = Route::getTemplates($method, $this->URI, $validController, $modelType);
        $templates[] = "model";

        $view = new View;
        $view->templates($templates);
        $view->acceptTypes($this->acceptedContentTypes);

        $view->set('model', $response->model);

        if ($viewOutput = $view->render()) {
            $response->content     = $viewOutput;
            $response->contentType = $view->getContentType();
        } else {
            $response->content     = print_r($model, true);
            $response->contentType = 'text/plain';
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