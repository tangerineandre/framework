<?php
use Phidias\Route;


/*
Basic behavior:

Given a request:  GET foo/bar/shoo

Look for controller as
a) Foo_Bar_Shoo_Controller::get()
b) Foo_Bar_Controller::getShoo()
c) Foo_Controller::getBar('shoo')

*/
Route::forRequest('*')->useController(function($requestMethod, $requestResource) {

    $parts              = explode('/', $requestResource);

    $class              = FALSE;
    $method             = $requestMethod;
    $arguments          = array();

    $found              = FALSE;
    $maxLoop            = 15;

    while ( !$found && count($parts) && $maxLoop-- ) {

        /* Best scenario: a class exists with the full resource name (e.g. for resource foo/bar/shoo  a class Foo_Bar_Shoo_Controller exists) */
        $class = implode('_', array_map('ucfirst', $parts)).'_Controller';
        if (class_exists($class)) {
            $found = TRUE;
            break;
        }

        /* Second possibility: Last part of the resource is the class's method
        e.g. for resource foo/bar/shoo a class Foo_Bar_Controller exists with method "getShoo" (or "postShoo", ....)
         */
        $lastPart    = array_pop($parts);
        $maybeMethod = $requestMethod.ucfirst($lastPart);
        $class       = implode('_', array_map('ucfirst', $parts)).'_Controller';
        if (is_callable(array($class, $maybeMethod))) {
            $found  = TRUE;
            $method = $maybeMethod;
            break;
        }

        /* Finally, assume the last part was an argument */
        $arguments[] = $lastPart;
    }

    if (!$found) {
        return NULL;
    }

    return array($class, $method, array_reverse($arguments));

});



/*
Determine template from controller

i.e.

type                                        template
---------------------------------------------------
Person_Foo_Controller->get()                person/foo/get
Person_Foo_Controller->getShoo()            person/foo/getshoo


*/
Route::forRequest('*')->useTemplate(function($requestMethod, $requestResource, $controller, $modelType) {

    $baseParts = explode('_', strtolower(str_replace('_Controller', '', $controller[0])));
    $template  = implode('/', $baseParts).'/'.strtolower($controller[1]);

    return $template;

});


/*
Determine template from model type.

i.e.

type                        template
---------------------------------------------------
array                       types/array
string                      types/string
Phidias\ORM\Collection      types/phidias/orm/collection
... see the pattern ?

*/
Route::forRequest('*')->useTemplate(function($requestMethod, $requestResource, $controller, $modelType) {

    $parts = explode('\\', trim(strtolower($modelType)));
    return 'types/'.implode('/', $parts);

});