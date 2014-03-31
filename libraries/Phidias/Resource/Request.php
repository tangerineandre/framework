<?php
namespace Phidias\Resource;

use Phidias\HashTable;

class Request
{
	public $method;
	public $data;    //PHP variable	
	public $headers; //HashTable

	public function __construct($method, $data = NULL, $headers = NULL)
	{
		$this->method  = $method;
		$this->data    = $data;
		$this->headers = new HashTable($headers);
	}

}