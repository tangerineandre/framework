<?php
namespace Phidias;

class Exception extends \Exception
{
	protected $code = 400;
	protected $data;

    public function __construct($data = NULL, $message = NULL, $previous = NULL)
    {
        parent::__construct($message, $this->code, $previous);
        $this->data = $data;
    }

    public function getData()
    {
    	return $this->data;
    }

    public function toJSON()
    {
    	return json_encode(array(
    		'exception'	=> get_class($this),
    		'data'		=> $this->data,
    		'message'	=> $this->getMessage(),
    		'code'		=> $this->getCode()
    	));
    }
}