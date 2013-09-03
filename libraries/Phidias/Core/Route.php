<?php
namespace Phidias\Core;

class Route
{
    private static $index;

    public static function load($routes)
    {
        self::$index = array();

        foreach ($routes as $resource => $controllerData) {
            $parts = explode('/', $resource);
            $path =& self::$index;

            foreach ($parts as $part) {

                if (substr($part, 0, 1) == ':') {
                    $part = ':argument';
                }

                if (!isset($path[$part])) {
                    $path[$part] = array();
                }
                $path =& $path[$part];
            }

            $path['_controller'] = $controllerData;
        }
    }

    private static function parseRoute($resource, $requestMethod = 'get')
    {
        $parts      = explode('/', $resource);
        $arguments  = array();
        $path       = self::$index;
        foreach ($parts as $part) {
            if (isset($path[$part])) {
                $path = $path[$part];
            } else if (isset($path[':argument'])) {
                $arguments[] = $part;
                $path = $path[':argument'];
            } else {
                return false;
            }
        }

        if (isset($path['_controller'])) {
            $class      = $path['_controller'][0];
            $method     = $path['_controller'][1];

            if ( is_callable(array($class, $method.'_'.$requestMethod)) ) {
                $method = $method.'_'.$requestMethod;
            }

            return array($resource, $class, $method, $arguments);
        }

        return false;
    }

    public static function controller($resource, $requestMethod = 'get')
    {
        Debug::startBlock("mapping to '$resource' using route definitions");
        $parsed = self::parseRoute($resource, $requestMethod);
        Debug::endBlock();

        if ($parsed) {
            return $parsed;
        }


        Debug::startBlock("mapping to '$resource' using naming conventions");

        $parts              = explode('/', $resource);

        $controller         = $resource;
        $class              = FALSE;
        $method             = FALSE;
        $arguments          = array();

        $found              = FALSE;
        $maxLoop            = 15;

        while ( !$found && count($parts) && $maxLoop-- ) {

            Debug::add("looking for controller '$controller'");

            $class = implode('_', array_map('ucfirst', $parts)).'_Controller';

            $method = 'main_'.$requestMethod;
            Debug::add("as $class->$method()");
            if ( is_callable(array($class, $method)) ) {
                $found = TRUE;
                break;
            }

            $method = 'main';
            Debug::add("as $class->$method()");
            if ( is_callable(array($class, $method)) ) {
                $found = TRUE;
                break;
            }

            $partsPop       = array_pop($parts);
            $expectedMethod = strtolower($partsPop);

            if ( !count($parts) ) {
                break;
            }

            $class = implode('_', array_map('ucfirst', $parts)).'_Controller';

            $method = $expectedMethod.'_'.$requestMethod;
            Debug::add("as $class->$method()");
            if ( is_callable(array($class, $method)) ) {
                $found = TRUE;
                break;
            }

            $method = $expectedMethod;
            Debug::add("as $class->$method()");
            if ( is_callable(array($class, $method)) ) {
                $found = TRUE;
                break;
            }

            $arguments[]    = $partsPop;
            $controller     = strtolower( implode('/', $parts) );
        }

        if ( !$found ) {
            /* final attempt.... */
            $class  = 'Default_Controller';
            $method = $controller;

            Debug::add("finally as $class->$method()");
            if ( !is_callable(array($class, $method)) ) {
                Debug::add("route for '$resource' not found");
                Debug::endBlock();
                return FALSE;
            }
        }

        Debug::add("routed as $class->$method()");
        Debug::endBlock();
        return array($controller, $class, $method, array_reverse($arguments));
    }

    public static function template($resource, &$fileSource = NULL)
    {
        if (($languageCode = Language::getCode()) && Configuration::get('route.template.prefixLanguage')) {
            $targetFile = Environment::DIR_VIEWS."/".Configuration::get('view.format', 'html')."/".$languageCode."/$resource.".Configuration::get('view.extension', 'php');
            $retval = Environment::findFile($targetFile, $fileSource);
            if ($retval) {
                return $retval;
            }
        }

        $targetFile = Environment::DIR_VIEWS."/".Configuration::get('view.format', 'html')."/$resource.".Configuration::get('view.extension', 'php');
        return Environment::findFile($targetFile, $fileSource);
    }

    public static function layout($layout, &$fileSource = NULL)
    {
        if (($languageCode = Language::getCode()) && Configuration::get('route.layout.prefixLanguage')) {
            $retval = Environment::findFile(Environment::DIR_LAYOUTS.'/'.$languageCode.'/'.$layout, $fileSource);
            if ($retval) {
                return $retval;
            }
        }

        return Environment::findFile(Environment::DIR_LAYOUTS.'/'.$layout, $fileSource);
    }
}