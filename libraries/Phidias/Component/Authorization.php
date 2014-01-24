<?php
namespace Phidias\Component;

class Authorization implements AuthorizationInterface
{
    public static function authorized($class, $method, $arguments = array())
    {
        return TRUE;
    }
}