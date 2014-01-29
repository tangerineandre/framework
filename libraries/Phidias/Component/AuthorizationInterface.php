<?php
namespace Phidias\Component;

interface AuthorizationInterface
{
    public static function authorized($URI, $requestMethod);
}