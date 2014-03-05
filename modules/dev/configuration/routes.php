<?php
use Phidias\Resource\Route;

Route::forRequest("GET phidias/db/create")->useController(array('Phidias\DB\Controller', 'getCreate'));
Route::forRequest("GET phidias/db/drop")->useController(array('Phidias\DB\Controller', 'getDrop'));
Route::forRequest("GET phidias/db/recreate")->useController(array('Phidias\DB\Controller', 'getRecreate'));
Route::forRequest("GET phidias/db/triggers")->useController(array('Phidias\DB\Controller', 'getTriggers'));
Route::forRequest("GET phidias/db/truncate")->useController(array('Phidias\DB\Controller', 'getTruncate'));
Route::forRequest("GET phidias/db/optimize")->useController(array('Phidias\DB\Controller', 'getOptimize'));