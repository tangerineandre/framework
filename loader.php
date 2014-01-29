<?php
/* Global functions */
function dump($var, $returnOutput = FALSE) { return Phidias\Debug::dump($var, $returnOutput); }
function dumpx($var) { return Phidias\Debug::dumpx($var); }
function say($word) { return Phidias\Language::get($word); }

include 'libraries/SplClassLoader.php';
$coreLoader = new SplClassLoader(NULL, __DIR__.'/libraries');
$coreLoader->register();