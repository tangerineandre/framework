<?php
namespace Phidias\Util\Specification;

use Phidias\Core\Environment;
use Phidias\Core\Configuration;
use Phidias\Core\Debug;

class Object
{
    private static function getRootFolder()
    {
        $language = Configuration::get('environment.language');
        return $language ? "specification/$language/classes" : "specification/classes";
    }
    
    public static function get($class)
    {
        $filename           = self::getRootFolder().'/'.str_replace('_', '/', $class).".json";
        $specificationFile  = Environment::findFile($filename);
        
        if ($specificationFile === NULL) {
            Debug::add("specification file for '$class' ($filename) not found");
            return NULL;
        }
        
        return json_decode(file_get_contents($specificationFile));
    }
}