<?php
namespace Phidias\Component;

interface LanguageInterface
{
	public static function load($languageCode, $context = NULL);
    public static function useContext($source);
    public static function getCurrentContext();
    public static function getCode();
    public static function set($words, $context = NULL);
    public static function get($word);
}