<?php
namespace Phidias\Component;

interface ConfigurationInterface
{
	public static function load();
    public static function get($variable, $defaultValue = NULL);
    public static function getAll($prefix = NULL);
    public static function set($variable, $value);
    public static function setAll($variables);
    public static function getObject($prefix);
}