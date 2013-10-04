<?php
namespace Phidias\ORM;

class Entity
{
    protected static $map;

    /* Primary key values as stored in the database.  If this value is NULL, the object is assumed to not be stored yet */
    private $_id;

    public function __construct($_id = NULL, $autoFetch = TRUE)
    {
        if ($_id !== NULL) {
            if ($autoFetch) {
                $probe = self::single()->allAttributes()->find($_id);
                $this->setValues((array)$probe);
            }
            $this->_id = $_id;
        }
    }

    public function getID()
    {
        return $this->_id;
    }

    public function setID($_id)
    {
        $this->_id = $_id;

        $idValue    = (array)$_id;
        $map        = self::getMap();
        foreach ($map->getKeys() as $index => $attributeName) {
            $this->$attributeName = isset($idValue[$index]) ? $idValue[$index] : NULL;
        }

    }


    public static function getMap()
    {
        $className = get_called_class();
        return new Entity\Map($className::$map);
    }

    public static function collection()
    {
        $className = get_called_class();
        return new Collection(new $className, self::getMap());
    }

    public static function single()
    {
        $className = get_called_class();
        return new Collection(new $className, self::getMap(), TRUE);
    }

    public static function table()
    {
        return new Table(self::getMap());
    }

    public function setValues(array $values)
    {
        $map = self::getMap();

        foreach ($values as $attribute => $value) {

            if (!$map->hasAttribute($attribute)) {
                continue;
            }

            if ($value === NULL) {
                continue;
            }

            if ($map->hasRelation($attribute) && is_array($value)) {
                if (!($this->$attribute instanceof Entity)) {
                    $relationData = $map->getRelation($attribute);
                    $this->$attribute = new $relationData['entity'];
                }
                $this->$attribute->setValues($value);
            } else {
                $this->$attribute = $value;
            }

        }
    }

    public function toArray()
    {
        return (array)$this;
    }

    public function toJSON()
    {
        return json_encode($this);
    }


    /* Shorthand methods */
    private function getCollection()
    {
        $map = self::getMap();
        $collection = self::single()->allAttributes();

        foreach (array_keys($map->getRelations()) as $attributeName) {
            if (isset($this->$attributeName) && $this->$attributeName instanceof Entity) {
                $collection->attr($attributeName, $this->$attributeName->getCollection());
            }
        }

        return $collection;
    }

    public function save()
    {
        return $this->getCollection()->save($this);
    }

    public function delete()
    {
        return self::single()->delete($this);
    }

}