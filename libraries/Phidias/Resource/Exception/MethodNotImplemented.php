<?php
namespace Phidias\Resource\Exception;

class MethodNotImplemented extends \Phidias\Exception
{
	protected $code = 501; //HTTP 501: Not implemented
}