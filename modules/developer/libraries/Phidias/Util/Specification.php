<?php
namespace Phidias\Util;

class Specification
{
    public static function get($element)
    {
        return NULL;
    }

    public static function factory($class)
    {
        $className = "Phidias\Util\Specification\\$class";

        return new $className;
    }
}