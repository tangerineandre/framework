<?php
namespace Phidias\Component;

interface SessionInterface
{
    public static function set($varname, $value = NULL);
    public static function get($varname, $default = NULL);
    public static function clear($varname);
    public static function getAll();
    public static function destroy();
}