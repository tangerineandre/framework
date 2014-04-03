<?php

/*
Route declaration syntax:

Route::forRequest("[REQUEST METHOD] [REQUEST RESOURCE]");
useController(array([CONTROLLER CLASS], [CONTROLLER METHOD], [CONTROLLER ARGUMENTS]))


examples:

//Run myControllerClass()->controllerMethod("foo", "bar") on GET some/resource
Route::forRequest("GET some/resource")
     ->useController(array("myControllerClass", "controllerMethod", array("foo", "bar")));

//Multiple methods:
Route::forRequest("GET|POST some/resource") [...]

//Any method
Route::forRequest("* some/resource") [...]

//Arguments
Route::forRequest("GET books/:bookId")
     ->useController(array("myControllerClass", "controllerMethod", array(":bookId")));

//Wildcards
//e.g. Run myControllerClass()->getBook(123) on GET books/123
// and myControllerClass()->postBook(123) on GET some/resource
Route::forRequest("GET|POST books/:bookId")
     ->useController(array("myControllerClass", "\$methodBook", array(":bookId")));

//e.g. Run myControllerClass()->do("get", 123) on GET books/123
Route::forRequest("GET books/:bookId")
     ->useController(array("myControllerClass", "do", array("\$methodBook", ":bookId")));


//Custom routing functions
//$requestMethod: e.g. "get"
//$requestResource: e.g. "books/3"

//return: array([CONTROLLER CLASS], [CONTROLLER METHOD], [CONTROLLER ARGUMENTS])

$myRoutingFunction = function($requestMethod, $requestResource) {
};

Route::forRequest(...)
       ->useController($myRoutingFunction);

*/





use Phidias\Resource\Route;


//Basic behavior:

//Giden a request:   GET foo/bar/shoo


/* Run Foo\Controller::getBar('shoo') */
Route::forRequest('*')->useController(function($requestMethod, $requestResource) {

    $parts     = explode('/', $requestResource);

    if (count($parts) < 3) {
        return NULL;
    }

    $argument = array_pop($parts);
    $method   = $requestMethod.ucfirst(array_pop($parts));
    $class    = "\\" . implode('\\', array_map('ucfirst', $parts)).'\Controller';

    $arguments = array($argument);

    return array($class, $method, $arguments);
});


/* Run Foo\Bar\Controller::get('shoo') */
Route::forRequest('*')->useController(function($requestMethod, $requestResource) {

    $parts     = explode('/', $requestResource);

    if (count($parts) < 2) {
        return NULL;
    }

    $lastPart  = array_pop($parts);
    $class     = "\\" . implode('\\', array_map('ucfirst', $parts)).'\Controller';
    $method    = $requestMethod;
    $arguments = array($lastPart);

    return array($class, $method, $arguments);
});



/* Foo\Bar\Controller::getShoo() */
Route::forRequest('*')->useController(function($requestMethod, $requestResource) {

    $parts     = explode('/', $requestResource);

    if (count($parts) < 2) {
        return NULL;
    }

    $lastPart  = array_pop($parts);
    $class     = "\\" . implode('\\', array_map('ucfirst', $parts)).'\Controller';
    $method    = $requestMethod.ucfirst($lastPart);
    $arguments = array();

    return array($class, $method, $arguments);
});



/* Foo\Bar\Shoo\Controller::get() */
Route::forRequest('*')->useController(function($requestMethod, $requestResource) {

    $parts     = explode('/', $requestResource);
    $class     = "\\" . implode('\\', array_map('ucfirst', $parts)).'\Controller';
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
Route::forRequest('*')->useTemplate(function($requestMethod, $requestResource, $modelType) {

    $parts = explode('\\', trim(strtolower($modelType)));
    return 'types/'.implode('/', $parts);

});


Route::forRequest('*')->useTemplate(function($requestMethod, $requestResource, $modelType) {

    if ($parentClass = get_parent_class($modelType)) {
        $parts = explode('\\', trim(strtolower($parentClass)));
        return 'types/'.implode('/', $parts);
    }

});



/* Determine view from resource:
GET foo/bar  -->  foo/bar/get
*/

Route::forRequest('*')->useTemplate(function($requestMethod, $requestResource, $modelType) {

    return $requestResource.'/'.$requestMethod;

});