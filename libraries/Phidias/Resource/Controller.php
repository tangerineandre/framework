<?php
namespace Phidias\Resource;

class Controller
{
    protected $attributes;

    public function __construct($attributes = array())
    {
        $this->attributes = new Attributes($attributes);
    }

}