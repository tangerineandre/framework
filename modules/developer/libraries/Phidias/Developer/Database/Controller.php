<?php

namespace Phidias\Developer\Database;

use Phidias\Environment;
use Phidias\DB;
use Phidias\Component\Configuration;

class Controller extends \Phidias\Resource\Controller
{
    private static function getEntities($prefix = NULL)
    {
        $priorizedEntities = self::priorizeEntities(Environment::findClasses('Entity'));

        if ($prefix) {
            foreach ($priorizedEntities as $className => $object) {
                if (stripos($className, $prefix) !== 0) {
                    unset($priorizedEntities[$className]);
                }
            }
        }

        return $priorizedEntities;
    }

    private static function priorizeEntities($classnames, &$organized = array(), &$checking = array())
    {
       
        foreach ($classnames as $classname) {

            $classname = trim($classname, "\\");

            if (isset($checking[$classname])) {
                continue;
            }

            $checking[$classname] = true;

            //!!!
            if (strpos($classname, "V3") !== false) {
                continue;
            }

            if (!class_exists($classname)) {
                continue;
            }

            if (!is_subclass_of($classname, "\Phidias\ORM\Entity")) {
                continue;
            }

            $object = new $classname;
            $map    = $object->getMap();
            $db     = $map->getDB();

            $relations = array();
            foreach ($map->getRelations() as $relationData) {
                $relations[] = $relationData['entity'];
            }

            self::priorizeEntities($relations, $organized, $checking);

            $organized[$classname] = $object;

        }

        return $organized;
    }

    private static function createDatabase($identifier)
    {
        if ($identifier === 'default') {

            $credentials = array(
                'host'     => Configuration::get("phidias.db.host"),
                'username' => Configuration::get("phidias.db.username"),
                'password' => Configuration::get("phidias.db.password"),
                'database' => Configuration::get("phidias.db.database"),                
                'charset'  => Configuration::get("phidias.db.charset")
            );

        } else {
            $credentials = Configuration::getAll("phidias.db.$identifier.");
        }

        if (!$credentials || !isset($credentials['database'])) {
            return FALSE;
        }

        $databaseName = $credentials['database'];

        $db = DB::connect(array(
            'host'     => isset($credentials['host'])       ? $credentials['host']      : null,
            'username' => isset($credentials['username'])   ? $credentials['username']  : null,
            'password' => isset($credentials['password'])   ? $credentials['password']  : null,
            'charset'  => isset($credentials['charset'])    ? $credentials['charset']   : null,
        ));

        $db->query("CREATE DATABASE IF NOT EXISTS `$databaseName` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci");

        return TRUE;
    }

    public function getCreate()
    {
        $entities  = self::getEntities($this->attributes->get('prefix'));

        $databases = array();

        foreach ($entities as $entity) {

            $databaseName = $entity->getMap()->getDB();

            if (!$databaseName) {
                $databaseName = 'default';
            }

            if (!isset($databases[$databaseName])) {
                $databases[$databaseName] = self::createDatabase($databaseName);
            }


            $entity::table()->create();
        }

        foreach ($entities as $entity) {
            $entity::table()->createTriggers();
        }
    }

    public function getDrop()
    {
        $entities = self::getEntities($this->attributes->get('prefix'));

        foreach (array_reverse($entities) as $entity) {
            $entity::table()->drop();
        }
    }


    public function getRecreate()
    {
        $entities = self::getEntities($this->attributes->get('prefix'));

        foreach (array_reverse($entities) as $entity) {
            $entity::table()->drop();
        }

        foreach ($entities as $entity) {
            $entity::table()->create();
        }

        foreach ($entities as $entity) {
            $entity::table()->createTriggers();
        }
    }


    public function getTriggers()
    {
        $entities = self::getEntities($this->attributes->get('prefix'));

        foreach ($entities as $entity) {
            $entity::table()->createTriggers();
        }
    }

    public function getTruncate()
    {
        $entities = self::getEntities($this->attributes->get('prefix'));

        foreach (array_reverse($entities) as $entity) {
            $entity::table()->clear();
        }
    }

    public function getOptimize()
    {
        $entities = self::getEntities($this->attributes->get('prefix'));

        foreach ($entities as $entity) {
            $table = $entity::table();
            $table->defragment();
            $table->optimize();
        }
    }
}