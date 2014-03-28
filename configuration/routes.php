<?php
use Phidias\Resource\Route;



/*
Argument matching:

Route::forRequest("GET foo/bar/:var1/:var2/*rest")
    ->useController(array("Controller_:var1", ":var2", array("foo", ":var2", "bar", "*rest")));
*/

Route::forRequest('* default')->useController(array('Phidias\Application\Main\Controller', 'get'));



/*
Basic behavior:

Given a request:  GET foo/bar/shoo
*/ 


/* d) Foo\Controller::getBar('shoo') */
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


/* c) Foo\Bar\Controller::get('shoo') */
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



/* b) Foo\Bar\Controller::getShoo() */
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



/* a) Foo\Bar\Shoo\Controller::get() */
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
Route::forRequest('*')->useTemplate(function($requestMethod, $requestResource, $controller, $modelType) {

    $parts = explode('\\', trim(strtolower($modelType)));
    return 'types/'.implode('/', $parts);

});


Route::forRequest('*')->useTemplate(function($requestMethod, $requestResource, $controller, $modelType) {

    if ($parentClass = get_parent_class($modelType)) {
        $parts = explode('\\', trim(strtolower($parentClass)));
        return 'types/'.implode('/', $parts);
    }

});




/*
Determine template from controller

i.e.

type                                        template
---------------------------------------------------
Person\Foo\Controller->get()                person/foo/get
Person\Foo\Controller->getShoo()            person/foo/getshoo


*/
Route::forRequest('*')->useTemplate(function($requestMethod, $requestResource, $controller, $modelType) {

    $baseParts = explode('\\', strtolower(str_replace('\Controller', '', $controller[0])));
    $template  = implode('/', $baseParts).'/'.strtolower($controller[1]);

    return $template;

});


