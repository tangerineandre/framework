<?php
namespace Phidias\Component;

/*
This class defines a resource and its attributes,
and defines how request methods are executed.

A resource is composed of:
URI: http://en.wikipedia.org/wiki/Uniform_resource_identifier
attributes: an array of attribute names and values

Usage:

//Perform a  GET request on foo?limit=20
$resource = new Resource('foo', array('limit' = 20));



The output is given in a standard mime format.
A list of aceepted response formats can be specified with
(basically the Accept header data)

$resource->acceptContentType(array(
    'text/html' => 1,
    'application/xhtml+xml' => 1,
    'application/xml' => 0.9
));



$output = $resource->run('get');

$outputFormat = $resource->getContentType();







*/


interface ResourceInterface
{
    public function __construct($resource, $attributes = NULL);
    public function acceptContentType($mimeTypes);
    public function run($requestMethod = NULL);
    public function getContentType();
}