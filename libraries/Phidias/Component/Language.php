<?php
namespace Phidias\Component;

use Phidias\Core\Debug;
use Phidias\Core\Filesystem;

class Language implements LanguageInterface
{
    private static $code            = NULL;
    private static $words           = array();
    private static $currentContext  = NULL;

    public static function load($languageCode, $context = NULL)
    {
        self::$code = $languageCode;

        $dictionaryFolder   = $context."/languages/$languageCode";
        $files              = Filesystem::listDirectory($dictionaryFolder, 1, 0);

        foreach ($files as $dictionaryFile) {

            Debug::startBlock("loading language file '$dictionaryFile'", 'include');

            $words = include $dictionaryFolder.'/'.$dictionaryFile;
            if (is_array($words)) {
                self::set($words, $context);
            }

            Debug::endBlock();
        }   
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
            $context = 'default';
        }

        if (!isset(self::$words[$context])) {
            self::$words[$context] = $words;
        } else {
            self::$words[$context] = array_merge(self::$words[$context], $words);
        }
    }

    public static function get($word)
    {
        $context = self::$currentContext === NULL ? 'default' : self::$currentContext;

        return isset(self::$words[$context][$word]) ? self::$words[$context][$word] : $word;
    }
}