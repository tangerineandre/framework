<?php
include 'loader.php';
$classLoader = new SplClassLoader(NULL, __DIR__.'/libraries');
$classLoader->register();

use Phidias\Core\Debug;
use Phidias\Core\Environment;
use Phidias\Core\Application;
use Phidias\Component\ExceptionHandler;

if (isset($_GET['__debug'])) {
    Debug::enable();
}

Environment::initialize();

try {

    Application::initialize();
    echo Application::execute();
    Application::finalize();

} catch ( Exception $e ) {
    echo ExceptionHandler::handle($e);
}

Environment::finalize();


/* Global functions */
function dump($var, $returnOutput = FALSE) { Phidias\Core\Debug::dump($var, $returnOutput); }
function dumpx($var) { Phidias\Core\Debug::dumpx($var); }
function say($word) { return Phidias\Core\Language::get($word); }