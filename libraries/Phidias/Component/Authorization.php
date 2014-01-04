<?php
namespace Phidias\Component;

class Authorization
{
    public static function authorized($class, $method, $arguments = array())
    {
        return TRUE;
    }
}