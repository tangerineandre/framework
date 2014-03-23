<?php
use Phidias\Resource\Route;

Route::forRequest('* default')->useController(array('Phidias\Documentation\Controller', 'get'));