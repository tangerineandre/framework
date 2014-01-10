<?php
use Phidias\Core\Controller;
use Phidias\Core\Environment;

class Phidias_Orm_Controller extends Controller
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

            if (strpos($classname, 'Phidias_ORM') === 0) {
                continue;
            }

            if (isset($checking[$classname])) {
                continue;
            }

            $checking[$classname] = true;

            $object = new $classname;
            if (!$object instanceof \Phidias\ORM\Entity) {
                continue;
            }

            $map    = $object->getMap();
            $db     = $map->getDB();
            if ($db !== NULL) {
                continue;
            }

            $relations = array();
            foreach ($map->getRelations() as $relationData) {
                $relations[] = $relationData['entity'];
            }

            self::priorizeEntities($relations, $organized, $checking);

            $organized[$classname] = $object;

        }

        return $organized;
    }

    public function create()
    {
        $entities = self::getEntities($this->attributes->get('prefix'));

        foreach ($entities as $entity) {
            $entity::table()->create();
        }

        foreach ($entities as $entity) {
            $entity::table()->createTriggers();
        }
    }

    public function drop()
    {
        $entities = self::getEntities($this->attributes->get('prefix'));

        foreach (array_reverse($entities) as $entity) {
            $entity::table()->drop();
        }
    }

    public function triggers()
    {
        $entities = self::getEntities($this->attributes->get('prefix'));

        foreach ($entities as $entity) {
            $entity::table()->createTriggers();
        }
    }

    public function truncate()
    {
        $entities = self::getEntities($this->attributes->get('prefix'));

        foreach (array_reverse($entities) as $entity) {
            $entity::table()->clear();
        }
    }

    public function optimize()
    {
        $entities = self::getEntities($this->attributes->get('prefix'));

        foreach ($entities as $entity) {
            $table = $entity::table();
            $table->defragment();
            $table->optimize();
        }
    }
}