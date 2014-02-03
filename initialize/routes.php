<?php
use Phidias\Resource\Route;

/*
Basic behavior:

Given a request:  GET foo/bar/shoo
*/ 


/* d) Foo_Controller::getBar('shoo') */
Route::forRequest('*')->useController(function($requestMethod, $requestResource) {

    $parts     = explode('/', $requestResource);

    if (count($parts) < 3) {
        return NULL;
    }

    $argument = array_pop($parts);
    $method   = $requestMethod.ucfirst(array_pop($parts));
    $class    = implode('_', array_map('ucfirst', $parts)).'_Controller';

    $arguments = array($argument);

    return array($class, $method, $arguments);
});


/* c) Foo_Bar_Controller::get('shoo') */
Route::forRequest('*')->useController(function($requestMethod, $requestResource) {

    $parts     = explode('/', $requestResource);

    if (count($parts) < 2) {
        return NULL;
    }

    $lastPart  = array_pop($parts);
    $class     = implode('_', array_map('ucfirst', $parts)).'_Controller';
    $method    = $requestMethod;
    $arguments = array($lastPart);

    return array($class, $method, $arguments);
});



/* b) Foo_Bar_Controller::getShoo() */
Route::forRequest('*')->useController(function($requestMethod, $requestResource) {

    $parts     = explode('/', $requestResource);

    if (count($parts) < 2) {
        return NULL;
    }

    $lastPart  = array_pop($parts);
    $class     = implode('_', array_map('ucfirst', $parts)).'_Controller';
    $method    = $requestMethod.ucfirst($lastPart);
    $arguments = array();

    return array($class, $method, $arguments);
});



/* a) Foo_Bar_Shoo_Controller::get() */
Route::forRequest('*')->useController(function($requestMethod, $requestResource) {

    $parts     = explode('/', $requestResource);
    $class     = implode('_', array_map('ucfirst', $parts)).'_Controller';
    $method    = $requestMethod;
    $arguments = array();

    return array($class, $method, $arguments);

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


