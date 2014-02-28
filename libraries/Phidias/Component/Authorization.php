<?php
namespace Phidias\Component;

class Authorization implements AuthorizationInterface
{
    public static function authorized($URI, $requestMethod, $requestHeaders = null)
    {
        return TRUE;
    }
}