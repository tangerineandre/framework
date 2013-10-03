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
        foreach ($map['keys'] as $index => $attributeName) {
            $this->$attributeName = isset($idValue[$index]) ? $idValue[$index] : NULL;
        }

    }


    public static function getMap()
    {
        $className = get_called_class();
        return Entity\Map::sanitize($className::$map);
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

        foreach ($values as $key => $value) {

            if (!isset($map['attributes'][$key])) {
                continue;
            }

            if (isset($this->$key) && ($this->$key instanceof Entity)) {
                $this->$key->setValues($value);
            } else {
                $this->$key = $value;
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
    public function save()
    {
        return self::single()->allAttributes()->save($this);
    }

    public function delete()
    {
        return self::single()->delete($this);
    }

}