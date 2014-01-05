<?php
namespace Phidias\Component;

interface Authorization_Interface
{
    public static function authorized($class, $method, $arguments = array());
}