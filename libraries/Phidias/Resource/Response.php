<?php

namespace Phidias\Resource;

class Response
{
	public $model;

	public $code;
	public $message;
	public $content;
	public $contentType;

	public function __construct()
	{
		$this->model       = null;

		$this->code        = 200;
		$this->message     = 'OK';
		$this->content     = null;
		$this->contentType = null;
	}
}