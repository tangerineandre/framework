<?php
namespace Phidias\Resource;

use Phidias\HashTable;

class Request
{
	public $method;
	public $headers; //HashTable
	public $body;

	public $data; //body rendered as a PHP variable

	public function construct($method, $data = NULL, $headers = NULL)
	{
		$this->method  = $method;
		$this->data    = $data;
		$this->headers = new HashTable($headers);
	}

}