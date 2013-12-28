<?php
include 'loader.php';
$classLoader = new SplClassLoader(NULL, __DIR__.'/libraries');
$classLoader->register();

use Phidias\Core\Debug;
use Phidias\Core\Environment;
use Phidias\Core\Application;
use Phidias\Core\Configuration;
use Phidias\Component\ExceptionHandler;
use Phidias\Component\HTTP\Request;

if (isset($_GET['__debug'])) {
    Debug::enable();
}

Environment::initialize();

try {

    Application::initialize();

    $resource   = Request::GET('_a', Configuration::get('controller.default'));
    $attributes = Request::GET();
    unset($attributes['_a']);
    Debug::add("using default resource: $resource");

    echo Application::run($resource, $attributes);

    Application::finalize();

} catch ( Exception $e ) {
    echo ExceptionHandler::handle($e);
}

Environment::finalize();


/* Global functions */
function dump($var, $returnOutput = FALSE) { return Phidias\Core\Debug::dump($var, $returnOutput); }
function dumpx($var) { return Phidias\Core\Debug::dumpx($var); }
function say($word) { return Phidias\Core\Language::get($word); }