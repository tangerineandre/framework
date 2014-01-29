<?php
namespace Phidias\Component;

use Phidias\Environment;
use Phidias\Debug;

class Configuration implements ConfigurationInterface
{
    private static $variables = array();

    public static function load()
    {
        /* Include every file in the configuration folder.  If the included file returns an array, load it as configuration variables */
        Debug::startBlock('including configuration files');
        $configurationFiles = Environment::listDirectory(Environment::DIR_CONFIGURATION, TRUE, FALSE);

        foreach ($configurationFiles as $configurationFile) {

            /* Ignore configuration files prefixed with "_" */
            if (substr(basename($configurationFile), 0, 1) == '_') {
                continue;
            }

            Debug::startBlock("loading configuration from '$configurationFile'", 'include');

            $retval = include $configurationFile;
            if (is_array($retval)) {
                self::setAll($retval);
            }

            Debug::endBlock();
        }
        Debug::endBlock();
    }

    public static function get($variable, $defaultValue = NULL)
    {
        return isset(self::$variables[$variable]) ? self::$variables[$variable] : $defaultValue;
    }

    public static function getAll($prefix = NULL)
    {
        if ($prefix) {

            $retval = array();
            $len    = strlen($prefix);

            foreach (self::$variables as $name => $value) {
                if (substr($name, 0, $len) == $prefix) {
                    $retval[substr($name,$len)] = $value;
                }
            }

            return $retval;
        }

        return self::$variables;
    }

    public static function set($variable, $value)
    {
        self::$variables[$variable] = $value;
    }

    public static function setAll($array)
    {
    	self::$variables = array_merge(self::$variables, $array);
    }

    public static function getObject($objectName)
    {
        $retval = new \stdClass;

        foreach (self::getAll("$objectName.") as $variableName => $value) {
            self::setObjectProperty($retval, $variableName, $value);
        }

        return $retval;
    }

    private static function setObjectProperty($object, $propertyName, $value)
    {
        $parts    = is_array($propertyName) ? $propertyName : explode('.', $propertyName);
        $property = array_shift($parts);

        if (!count($parts)) {
            $object->$property = $value;
            return;
        }

        if (!isset($object->$property)) {
            $object->$property = new \stdClass;
        }

        self::setObjectProperty($object->$property, $parts, $value);
    }
}