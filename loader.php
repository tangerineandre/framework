<?php
/* Global functions */
function dump($var, $returnOutput = FALSE) { return Phidias\Core\Debug::dump($var, $returnOutput); }
function dumpx($var) { return Phidias\Core\Debug::dumpx($var); }
function say($word) { return Phidias\Core\Language::get($word); }

include 'libraries/SplClassLoader.php';
$coreLoader = new SplClassLoader(NULL, __DIR__.'/libraries');
$coreLoader->register();