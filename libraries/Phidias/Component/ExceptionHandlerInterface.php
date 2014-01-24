<?php
namespace Phidias\Component;

interface ExceptionHandlerInterface
{
    public static function handle(\Exception $e);
}