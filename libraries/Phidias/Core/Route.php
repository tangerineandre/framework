<?php
/*
Routing component

Route::addRule($resourcePattern, $method, $class, $method, $additionalArguments)

e.g.
Route::addRule('groups/roles', NULL, 'Person_Role_Controller', 'collection');




Route matching should be as fast as possible.  To do this we will have a pattern index, like this:

Suppose the following routes are added:

people/:personID        -> Person_Controller::details()
people/foo              -> Person_Controller::foo()
people/bar              -> Person_Controller::bar()
people/bar/shoo         -> Person_Controller::shoo()
groups                  -> Group_Controller::collection()
groups/:groupID         -> Group_Controller::details()

The following index is created internally:

index = array(

    'people' => array(
        ':argument' => array(
            'method:*' => [invocable]
        ),

        'foo' => array(
            'method:*' => [invocable]
        ),

        'bar' => array(
            'method:*' => [invocable]

            'shoo' => array(
                'method:*' => [invocable]
            )
        )
    )


);





*/

namespace Phidias\Core;

class Route
{
    private static $index = array();

    /* Determine the invocable (class, method and arguments) corresponding to the given resource and request method */
    public static function controller($resource, $requestMethod = NULL)
    {
        $matchedUsingPattern = self::matchResource($resource, $requestMethod);

        return $matchedUsingPattern ? $matchedUsingPattern : self::matchName($resource, $requestMethod);
    }

    public static function addResource($requestMethod, $resourcePattern, $controllerClass, $controllerMethod, $additionalArguments = array())
    {
        $currentIndex   = &self::$index;
        $parts          = explode('/', $resourcePattern);
        foreach ($parts as $part) {

            if (substr($part, 0, 1) == ':') {
                $part = ':argument';
            }

            if (!isset($currentIndex[$part])) {
                $currentIndex[$part] = array();
            }

            $currentIndex = &$currentIndex[$part];
        }

        $currentIndex['method:'.$requestMethod] = array(
            'class'     => $controllerClass,
            'method'    => $controllerMethod,
            'arguments' => $additionalArguments
        );
    }


    private static function matchResource($resource, $requestMethod = NULL)
    {
        $currentIndex       = self::$index;
        $parts              = explode('/', $resource);
        $matchedArguments   = array();

        foreach ($parts as $part) {

            if (isset($currentIndex[$part])) {
                $currentIndex = $currentIndex[$part];
                continue;
            }

            if (isset($currentIndex[':argument'])) {
                $matchedArguments[] = $part;
                $currentIndex       = $currentIndex[':argument'];
                continue;
            }

            return NULL;
        }

        if ($requestMethod === NULL) {
            $requestMethod = '*';
        }

        if (isset($currentIndex['method:'.$requestMethod])) {

            $retval = $currentIndex['method:'.$requestMethod];

        } else if (isset($currentIndex['method:*'])) {

            $retval = $currentIndex['method:*'];
            if (is_callable(array($retval['class'], $retval['method'].'_'.$requestMethod))) {
                $retval['method'] = $retval['method'].'_'.$requestMethod;
            }

        } else {
            return NULL;
        }

        $retval['arguments'] = array_merge($matchedArguments, $retval['arguments']);

        return $retval;
    }


    /* Finds invocable via name matching (i.e. GET some/resource => Some_Controller::resource_get()  */
    public static function matchName($resource, $requestMethod = 'get')
    {
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
                return NULL;
            }
        }

        Debug::add("routed as $class->$method()");
        Debug::endBlock();

        return array(
            'class'     => $class,
            'method'    => $method,
            'arguments' => array_reverse($arguments)
        );

    }

    public static function template($templateResource, &$fileSource = NULL)
    {
        if (($languageCode = Language::getCode()) && Configuration::get('route.template.prefixLanguage')) {

            $targetFile = Environment::DIR_VIEWS."/".Configuration::get('view.format', 'html')."/".$languageCode."/$templateResource.".Configuration::get('view.extension', 'php');
            $retval     = Environment::findFile($targetFile, $fileSource);
            if ($retval) {
                return $retval;
            }

        }

        $targetFile = Environment::DIR_VIEWS."/".Configuration::get('view.format', 'html')."/$templateResource.".Configuration::get('view.extension', 'php');
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