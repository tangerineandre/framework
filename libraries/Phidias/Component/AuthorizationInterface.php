<?php
namespace Phidias\Component;

interface AuthorizationInterface
{
    public static function authorized($requestMethod, $requestResource, $requestHeaders = null);
}