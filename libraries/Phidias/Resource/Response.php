<?php

namespace Phidias\Resource;

use Phidias\HashTable;

class Response
{
	public $code;
	public $message;
	public $headers;
	public $contentType;
	public $body;

	public $data; //body rendered as a PHP variable

	public function __construct()
	{
		$this->code        = 200;
		$this->message     = 'OK';
		$this->headers     = new HashTable;
		$this->contentType = null;
		$this->body        = null;
		
		$this->data        = null;
	}
}