<?php
/*
Routing class

Esta clase determina la forma de asociar un REQUEST a un CONTROLADOR y una PLANTILLA.

e.g.

//declarative* syntax
Route::forRequest('GET people')->useController('Person_Controller->get()');

//alternatively:
//Route::forRequest($method, $resource)->useController(array('Person_Controller', 'get'));

//arguments
Route::forRequest('GET people/:personID')->useController('Person_Controller->get(:personID)');
//Route::forRequest($method, $resource)->useController(array('Person_Controller', 'get', array(':personID')));


Un CONTROLADOR se modela como un arreglo (sin llaves) con 3 elementos:
array($class, $method, $arguments = NULL)
e.g.: array("Person_Controller", "get")

*/

namespace Phidias\Resource;

use Phidias\HTTP\Request;
use Phidias\Debug;

use Phidias\Component\Configuration;
use Phidias\Component\Language;


class Route
{
    private static $controllerStorage;
    private static $templateStorage;

    private static function initialize()
    {
        if (self::$controllerStorage === NULL) {

            self::$controllerStorage = new Route\Storage(array(
                'requestMethod'     => 1,
                'resourcePattern'   => 2,
                'controller'        => 2
            ));

            self::$controllerStorage->useCompareFunction('requestMethod', array('Phidias\Resource\Route', 'matchesMethod'));
            self::$controllerStorage->useCompareFunction('resourcePattern', array('Phidias\Resource\Route', 'matchesPattern'));
        }


        if (self::$templateStorage === NULL) {

            self::$templateStorage = new Route\Storage(array(
                'requestMethod'   => 2,
                'resourcePattern' => 3,
                'controller'      => 2,
                'modelType'       => 1
            ));

            self::$templateStorage->useCompareFunction('requestMethod', array('Phidias\Resource\Route', 'matchesMethod'));
            self::$templateStorage->useCompareFunction('resourcePattern', array('Phidias\Resource\Route', 'matchesPattern'));

        }

    }


    public static function registerController($controller, $requestMethod = NULL, $resourcePattern = NULL)
    {
        self::initialize();

        self::$controllerStorage->store($controller, array(
            'requestMethod'   => $requestMethod,
            'resourcePattern' => $resourcePattern
        ));
    }

    public static function getControllers($requestMethod = NULL, $requestResource = NULL)
    {
        self::initialize();


        Debug::startBlock("routing controllers for request: '$requestMethod' resource: '$requestResource'");

        $matches = self::$controllerStorage->retrieve(array(
            'requestMethod'   => $requestMethod,
            'resourcePattern' => $requestResource
        ));

        foreach ($matches as $recordId => $matchingController) {

            if (gettype($matchingController) === 'object' && is_callable($matchingController)) {

                $builtController = call_user_func_array($matchingController, array($requestMethod, $requestResource));
                if (!$builtController) {
                    unset($matches[$recordId]);
                    continue;
                }
                $matches[$recordId] = $builtController;
                $controlerString = $matches[$recordId][0].'->'.$matches[$recordId][1].'() * generated from route function';

            } else {
                $controlerString = $matches[$recordId][0].'->'.$matches[$recordId][1].'()';
            }


            if (!isset($matches[$recordId][2]) || !is_array($matches[$recordId][2])) {
                $matches[$recordId][2] = array();
            }


            $wildcardValues = array(
                '$method' => $requestMethod
            );

            /* See if stored resource pattern contains any arguments */
            $recordAttributes = self::$controllerStorage->getRecordAttributes($recordId);
            if ($recordAttributes['resourcePattern'] !== NULL) {

                $matchedArguments = self::getMatchingArguments($recordAttributes['resourcePattern'], $requestResource);

                if ($matchedArguments !== NULL) {
                    $wildcardValues = array_merge($wildcardValues, $matchedArguments);
                }
            }

            /* Replace wildcard values in controller class, method and arguments */
            $wildcardKeys = array_keys($wildcardValues);

            $matches[$recordId][0] = str_replace($wildcardKeys, $wildcardValues, $matches[$recordId][0]);
            $matches[$recordId][1] = str_replace($wildcardKeys, $wildcardValues, $matches[$recordId][1]);

            foreach ($matches[$recordId][2] as &$argumentValue) {
                $argumentValue = str_replace($wildcardKeys, $wildcardValues, $argumentValue);
            }

            Debug::add("possible controller '$controlerString'");
        }

        Debug::endBlock();

        return $matches;
    }


    public static function registerTemplate($template, $requestMethod = NULL, $resourcePattern = NULL, $controller = NULL, $modelType = NULL)
    {
        self::initialize();

        self::$templateStorage->store($template, array(
            'requestMethod'   => $requestMethod,
            'resourcePattern' => $resourcePattern,
            'controller'      => $controller,
            'modelType'       => $modelType
        ));
    }


    public static function getTemplates($requestMethod = NULL, $requestResource = NULL, $controller = NULL, $modelType = NULL)
    {
        self::initialize();

        Debug::startBlock("routing template for request: '$requestMethod' resource: '$requestResource'");

        $matches = self::$templateStorage->retrieve(array(
            'requestMethod'   => $requestMethod,
            'resourcePattern' => $requestResource,
            'controller'      => $controller,
            'modelType'       => $modelType
        ));

        foreach ($matches as $key => $matchingTemplate) {

            if (is_callable($matchingTemplate)) {
                $builtTemplate = call_user_func_array($matchingTemplate, array($requestMethod, $requestResource, $controller, $modelType));
                if (!$builtTemplate) {
                    unset($matches[$key]);
                    continue;
                }
                $matches[$key] = $builtTemplate;
                $templateString = $matches[$key].' * generated from route function';
            } else {
                $templateString = $matches[$key];
            }

            Debug::add("possible template $templateString");
        }

        Debug::endBlock();

        return $matches;
    }






    /* Must indicate if a string conforms to a pattern:
    and return a scored value.  i.e.  string "foo/bar" matches both patterns "foo/:something" and "foo/bar"
    but "foo/bar" is a stronger match.

    pattern                     string                  isMatch
    foo                         foo                     1
    foo/bar                     foo/bar                 1
    foo/:var                    foo/x                   1
    foo/:var                    foo/y                   1
    foo/:var1/:var2             foo/x/y                 1
    foo/:var1/*remains          foo/a/1/2/3             1
    foo/:var1/*remains          foo/a                   0

    */
    public static function matchesPattern($pattern, $string)
    {
        $matchScore   = 0;
        
        $patternParts = explode('/', $pattern);
        $queryParts   = explode('/', $string);

        foreach ($queryParts as $key => $queryPart) {

            if (!isset($patternParts[$key])) {
                return 0;
            }

            $patternPart = $patternParts[$key];

            if (substr($patternPart, 0, 1) === '*') {
                return 1;
            }

            if (substr($patternPart, 0, 1) === ':') {
                $matchScore++;
                continue;
            }

            if ($queryPart !== $patternPart) {
                return 0;
            }

            $matchScore += 2;
        }

        return count($patternParts) == count($queryParts) ? $matchScore : 0;
    }

    //i.e.  matchesMethod('get|PosT|PUT', 'GET') --> true
    //i.e.  matchesMethod('get|PosT|PUT', 'deLETE') --> false
    public static function matchesMethod($pattern, $method)
    {
        return in_array(strtolower(trim($method)), explode('|', trim(strtolower($pattern))));
    }



    public static function getMatchingArguments($pattern, $string)
    {
        $matchedArguments = array();
        
        $patternParts     = explode('/', $pattern);
        $queryParts       = explode('/', $string);

        foreach ($patternParts as $key => $patternPart) {

            if (substr($patternPart, 0, 1) === '*') {
                $matchedArguments[$patternPart] = implode('/', array_slice($queryParts, $key));
                return $matchedArguments;
            }

            if (substr($patternPart, 0, 1) === ':') {
                $matchedArguments[$patternPart] = $queryParts[$key];
            }

        }

        return $matchedArguments;
    }




    /* Declarative syntax */
    private $requestMethod;
    private $resourcePattern;
    private $controller;
    private $modelType;

    public static function forRequest($requestString)
    {
        $retval = new Route;
        $retval->andRequest($requestString);

        return $retval;
    }

    public static function forController($controller)
    {
        $retval = new Route;
        $retval->andController($controller);

        return $retval;
    }

    public static function forModelType($type)
    {
        $retval = new Route;
        $retval->andModelType($type);

        return $retval;
    }


    public function andRequest($requestMethod, $resourcePattern = NULL)
    {
        //when invoked with a single string (e.g. Route::forRequest('GET person'))
        $requestString = trim($requestMethod);
        if ($requestString === '*') {
            $this->requestMethod   = NULL;
            $this->resourcePattern = NULL;
            return $this;
        }

        $parts                 = explode(' ', $requestString);
        $requestMethod         = trim($parts[0]);
        $resourcePattern       = trim($parts[1]);
        
        $this->requestMethod   = $requestMethod == '*' ? NULL : $requestMethod;
        $this->resourcePattern = $resourcePattern == '*' ? NULL : $resourcePattern;

        return $this;
    }

    public function andController($controller)
    {
        $this->controller = $controller;

        return $this;
    }

    public function andModelType($modelType)
    {
        $this->modelType = $modelType;

        return $this;
    }



    public function useController($controller)
    {
        self::registerController($controller, $this->requestMethod, $this->resourcePattern);

        return $this;
    }

    public function useTemplate($template)
    {
        self::registerTemplate($template, $this->requestMethod, $this->resourcePattern, $this->controller, $this->modelType);

        return $this;
    }

}