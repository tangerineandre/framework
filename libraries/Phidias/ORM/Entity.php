<?php
namespace Phidias\ORM;

class Entity
{
    protected static $map;

    public function getMap()
    {
        $className = get_called_class();
        return $className::$map;
    }

    public static function collection()
    {
        $className = get_called_class();
        return new Collection(new $className, $className::$map);
    }

    public static function single($primaryKeyValue = NULL)
    {
        $className = get_called_class();
        return new Collection(new $className, $className::$map, TRUE, $primaryKeyValue);
    }

    public static function table()
    {
        $className = get_called_class();
        return new Table($className::$map);
    }

    public function __construct($primaryKeyValue = NULL)
    {
        if ($primaryKeyValue !== NULL) {
            $entity = self::single($primaryKeyValue)->allAttributes()->find();
            if ($entity === NULL) {
                throw new Exception\EntityNotFound($primaryKeyValue);
            }

            $this->setValues((array)$entity);
        }
    }

    public function setValues(array $values)
    {
        foreach ($values as $key => $value) {
            $this->$key = $value;
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


    public function save()
    {
        $collection = self::collection();
        $collection->add($this);

        if ($collection->save() == 1) {
            $map = $this->getMap();

            foreach ($map['keys'] as $keyName) {
                if ( isset($map['attributes'][$keyName]['autoIncrement']) && $map['attributes'][$keyName]['autoIncrement'] ) {
                    $this->$keyName = $collection->getDB()->getInsertID();
                }
            }

            return TRUE;
        } else {
            return FALSE;
        }
    }


    public function getPrimaryKeyValues()
    {
        $map        = $this->getMap();
        $keyValues  = array();
        foreach ($map['keys'] as $keyName) {
            if (!isset($this->$keyName)) {
                return NULL;
            }

            $keyValues[] = $this->$keyName;
        }

        return $keyValues;
    }

    public function update()
    {
        $keyValues = $this->getPrimaryKeyValues();
        if ($keyValues === NULL) {
            trigger_error("attempt to update entity without key values");
            return 0;
        }

        return self::single($keyValues)->set($this->toArray())->update();
    }

    public function delete()
    {
        $keyValues = $this->getPrimaryKeyValues();
        if ($keyValues === NULL) {
            trigger_error("attempt to delete entity without key values");
            return 0;
        }

        return self::single($keyValues)->delete();
    }

}