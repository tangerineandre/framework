<?php
namespace Phidias\Component;

class Authorization implements AuthorizationInterface
{
    public static function authorized($requestMethod, $requestResource, $requestHeaders = null)
    {
        return TRUE;
    }
}