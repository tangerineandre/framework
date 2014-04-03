<?php
use Phidias\Resource\Route;

Route::forRequest('GET example/hello')
	   ->useController(array('Phidias\Example\myController', 'hello'));