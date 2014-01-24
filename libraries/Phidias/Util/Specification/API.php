<?php
namespace Phidias\Util\Specification;

use Phidias\Core\Environment;
use Phidias\Core\Configuration;

class API
{
    private static function getRootFolder()
    {
        $language = Configuration::get('environment.language');

        return $language ? "specification/$language/resources" : "specification/resources";
    }

    public static function get($resource)
    {
        $rootFolder = self::getRootFolder();

        $parts = explode('/', strtolower(trim($resource, '/')));
        $basename = array_pop($parts);

        $currentTrail = NULL;

        while (count($parts)) {

            $currentFolder = array_shift($parts);
            $targetFolder = $rootFolder.'/'.$currentTrail.'/'.$currentFolder;

            if (Environment::findFolder($targetFolder)) {
                $currentTrail .= $currentFolder.'/';
            } else {
                $wildcardFolder = $rootFolder.'/'.$currentTrail.'/_*';

                $candidates = Environment::glob($wildcardFolder, GLOB_ONLYDIR);
                if (!isset($candidates[0])) {
                    return NULL;
                }

                $candidate = $candidates[0];
                $currentFolder = substr($candidate, strrpos($candidate, '/')+1);
                $currentTrail .= $currentFolder.'/';
            }

        }

        $specificationFile = Environment::findFile($rootFolder.'/'.$currentTrail.$basename.'.json');

        if (!$specificationFile) {

            if ($currentTrail === NULL) {
                return NULL;
            }

            $candidates = Environment::glob($rootFolder.'/'.$currentTrail.'_*.json');
            if (!isset($candidates[0])) {
                return NULL;
            }

            $specificationFile = $candidates[0];
        }

        return json_decode(file_get_contents($specificationFile));
    }
}