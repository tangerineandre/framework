<?php
namespace Phidias\Core;

class Hook
{
    private $_controller;

    public function __construct($controller)
    {
        $this->_controller  = $controller;
    }

    public function get($variableName)
    {
        return $this->_controller->get($variableName);
    }
}