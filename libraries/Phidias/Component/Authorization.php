<?php
namespace Phidias\Component;

class Authorization implements Authorization_Interface
{
    public static function authorized($class, $method, $arguments = array())
    {
        return TRUE;
    }
}