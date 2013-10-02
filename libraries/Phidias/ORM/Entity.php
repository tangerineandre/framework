<?php
namespace Phidias\ORM;

class Entity
{
    protected static $map;


    private static function sanitizeMap($map)
    {
        if (!isset($map['attributes'])) {
            trigger_error('invalid map: no attributes defined', E_USER_ERROR);
        }

        if (!isset($map['keys'])) {
            trigger_error('invalid map: no keys defined', E_USER_ERROR);
        }

        foreach ($map['keys'] as $keyName) {
            if (!isset($map['attributes'][$keyName])) {
                trigger_error("invalid map: key '$keyName' is not defined as attribute", E_USER_ERROR);
            }
        }

        $map['relations'] = array();

        foreach ($map['attributes'] as $attributeName => &$attributeData) {
            if (!isset($attributeData['column'])) {
                $attributeData['column'] = $attributeName;
            }

            if (isset($attributeData['entity'])) {

                if (!class_exists($attributeData['entity'])) {
                    trigger_error("invalid map: related entity '{$attributeData['entity']}' not found", E_USER_ERROR);
                }

                if (!isset($attributeData['attribute'])) {
                    trigger_error("invalid map: no attribute specified for relation '$attributeName'", E_USER_ERROR);
                }

                $map['relations'][$attributeName] = $attributeData;
            }
        }

        if (!isset($map['db'])) {
            $map['db'] = NULL;
        }

        return $map;
    }


    public static function getMap()
    {
        $className = get_called_class();
        return self::sanitizeMap($className::$map);
    }

    public static function collection()
    {
        $className = get_called_class();
        return new Collection(new $className, self::getMap());
    }

    public static function single($primaryKeyValue = NULL)
    {
        $className = get_called_class();
        return new Collection(new $className, self::getMap(), TRUE, $primaryKeyValue);
    }

    public static function table()
    {
        return new Table(self::getMap());
    }

    public function __construct($primaryKeyValue = NULL)
    {
        if ($primaryKeyValue !== NULL) {
            $entity = self::single($primaryKeyValue)->allAttributes()->find();
            if ($entity === NULL) {
                throw new Exception\EntityNotFound(get_called_class(), $primaryKeyValue);
            }

            $this->setValues((array)$entity);
        }
    }

    public function setValues(array $values)
    {
        foreach ($values as $key => $value) {

            if (isset($this->$key) && ($this->$key instanceof Entity)) {

                $this->$key->setValues($value);
                try {
                    $this->$key->save();
                } catch (\Phidias\DB\Exception\DuplicateKey $e) {
                    //sssh, no worries
                }

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


    public function save()
    {
        $collection = self::collection();
        $collection->allAttributes();
        $collection->add($this);

        if ($collection->save() == 1) {
            $map = self::getMap();

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
        $map        = self::getMap();
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