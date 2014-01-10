<?php
namespace Phidias\Util\Specification;

use Phidias\Core\Environment;
use Phidias\Core\Configuration;

class Object
{
    private static function getRootFolder()
    {
        $language = Configuration::get('environment.language');
        return $language ? "specification/$language/class" : "specification/class";
    }
    
    public static function get($class)
    {
        $filename           = self::getRootFolder().'/'.str_replace('_', '/', $class).".json";
        $specificationFile  = Environment::findFile($filename);
        
        if ($specificationFile === NULL) {
            return NULL;
        }
        
        return json_decode(file_get_contents($specificationFile));
    }
}