<?php
namespace Phidias\Component;

class Authorization implements AuthorizationInterface
{
    public static function authorized($requestMethod, $URI)
    {
        return TRUE;
    }
}