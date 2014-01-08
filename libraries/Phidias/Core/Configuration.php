<?php
namespace Phidias\Core;

class Configuration
{
    private static $variables   = array();
    private static $sources     = array();

    public static function get($variable, $default_value = NULL, $source = NULL)
    {
        if ($source) {
            if ( !isset(self::$sources[$source]) ) {
                return $default_value;
            }

            return isset(self::$sources[$source][$variable]) ? self::$sources[$source][$variable] : $default_value;
        }

        return isset(self::$variables[$variable]) ? self::$variables[$variable] : $default_value;
    }

    public static function set($variable, $value = NULL, $source = NULL)
    {
        /* Assumed as Configuration::set($arrayOfVariables, $source) */
        if (is_array($variable)) {
            $source = $value;
            self::$variables = array_merge(self::$variables, $variable);

            if ($source) {
                if (!isset(self::$sources[$source])) {
                    self::$sources[$source] = $variable;
                } else {
                    self::$sources[$source] = array_merge(self::$sources[$source], $variable);
                }
            }

            return;
        }


        if ($source) {
            if ( !isset(self::$sources[$source]) ) {
                self::$sources[$source] = array();
            }
            self::$sources[$source][$variable] = $value;
        }

        self::$variables[$variable] = $value;
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
}