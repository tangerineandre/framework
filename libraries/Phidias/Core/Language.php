<?php
namespace Phidias\Core;

class Language
{
    private static $code            = NULL;
    private static $words           = array();
    private static $currentContext  = NULL;

    public static function useContext($source)
    {
        self::$currentContext = $source;
    }

    public static function getCurrentContext()
    {
        return self::$currentContext;
    }

    public static function setCode($code)
    {
        self::$code = $code;
    }

    public static function getCode()
    {
        return self::$code;
    }

    public static function set($words, $context = NULL)
    {
        if ($context === NULL) {
            $context = self::$currentContext;
        }

        $contextIndex = self::$currentContext !== NULL ? self::$currentContext : 'default';

        if (!isset(self::$words[$contextIndex])) {
            self::$words[$contextIndex] = $words;
        } else {
            self::$words[$contextIndex] = array_merge(self::$words[$contextIndex], $words);
        }
    }

    public static function get($word)
    {
        $contextIndex = self::$currentContext !== NULL ? self::$currentContext : 'default';

        return isset(self::$words[$contextIndex][$word]) ? self::$words[$contextIndex][$word] : $word;
    }
}