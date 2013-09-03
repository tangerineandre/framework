<?php
namespace Phidias\Core;

class Language
{
    private static $code        = NULL;
    private static $dictionary  = array();
    private static $sources     = array();

    private static $_currentSource  = NULL;

    public static function useSource($source)
    {
        self::$_currentSource   = $source;
    }

    public static function getCurrentSource()
    {
        return self::$_currentSource;
    }


    public static function setCode($code)
    {
        self::$code = $code;
    }

    public static function getCode()
    {
        return self::$code;
    }

    public static function get($word)
    {
        if (self::$_currentSource !== NULL && isset(self::$sources[self::$_currentSource][$word])) {
            return self::$sources[self::$_currentSource][$word];
        }

        return isset(self::$dictionary[$word]) ? self::$dictionary[$word] : $word;
    }

    public static function load($file, $source = NULL)
    {
        $words = include $file;

        if ($source !== NULL) {
            if (!isset(self::$sources[$source])) {
                self::$sources[$source] = $words;
            } else {
                self::$sources[$source] = array_merge(self::$sources[$source], $words);
            }
        }

        self::$dictionary = array_merge(self::$dictionary, $words);
    }
}