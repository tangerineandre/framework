<?php
namespace Phidias\Resource;

class Controller
{
    protected $attributes;
    protected $data;
    protected $response;

    public function __construct($response = null)
    {
    	$this->response = ($response === null) ? new Response : $response;
    }

    public function setAttributes($attributes)
    {
    	$this->attributes = new Attributes($attributes);
    }

    public function setData($data)
    {
    	$this->data = new Data($data);
    }

}