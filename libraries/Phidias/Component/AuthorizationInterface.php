<?php
namespace Phidias\Component;

interface AuthorizationInterface
{
    public static function authorized($class, $method, $arguments = array());
}