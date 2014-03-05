<?php
use Phidias\Resource\Route;

Route::forRequest("GET phidias/db/create")->useController(array('Phidias\DB\Controller', 'getCreate'));