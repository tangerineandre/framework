<?php
namespace Phidias\Component;

use Phidias\Core\Environment;
use Phidias\Core\Debug;

class Language implements LanguageInterface
{
    private static $code            = NULL;
    private static $words           = array();
    private static $currentContext  = NULL;

    public static function load($languageCode)
    {
        Debug::startBlock("loading language '$languageCode'");

        self::$code = $languageCode;
        $dictionaries = Environment::listDirectory(Environment::DIR_LANGUAGES."/$languageCode", TRUE, FALSE);
        foreach ($dictionaries as $dictionaryFile) {
            Debug::startBlock("loading language file '$dictionaryFile'", 'include');

            $words = include $dictionaryFile;
            if (is_array($words)) {
                $context = substr($dictionaryFile, 0, strpos($dictionaryFile, Environment::DIR_LANGUAGES."/$languageCode")-1);
                Language::set($words, $context);
            }

            Debug::endBlock();
        }

        Debug::endBlock();        
    }

    public static function useContext($source)
    {
        self::$currentContext = $source;
    }

    public static function getCurrentContext()
    {
        return self::$currentContext;
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