<?php
namespace Phidias\ORM;

use Phidias\DB;

class Entity
{
    protected static $map;

    private function getMap()
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
        return new Collection(new $className, $className::$map, TRUE);
    }

    public static function table()
    {
        $className = get_called_class();
        return new Table($className::$map);
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


    private function getIdentifyingCondition()
    {
        $map    = $this->getMap();
        $db     = DB::connect(isset($map['db']) ? $map['db'] : NULL);

        $allKeysAreSet = TRUE;
        $idConditions = array();
        foreach ($map['keys'] as $keyName) {
            if (!isset($this->$keyName)) {
                $allKeysAreSet = FALSE;
                break;
            }

            $columnName = isset($map['attributes'][$keyName]['name']) ? $map['attributes'][$keyName]['name'] : $keyName;
            $idConditions[] = "`".$columnName."` = ".$db->sanitizeValue($this->$keyName);
        }

        if (!$allKeysAreSet) {
            return FALSE;
        }

        return $idConditions ? implode(' AND ', $idConditions) : FALSE;
    }


    public function update()
    {
        $idCondition = $this->getIdentifyingCondition();

        if (!$idCondition) {
            return 0;
        }

        $collection = self::collection();

        $map = $this->getMap();
        foreach ($map['attributes'] as $attributeName => $attributeData) {
            if (!isset($this->$attributeName)) {
                continue;
            }

            $collection->set($attributeName, $this->$attributeName);
        }

        return $collection->where($idCondition)->update();
    }

    public function delete()
    {
        $idCondition = $this->getIdentifyingCondition();
        if (!$idCondition) {
            return 0;
        }

        return self::collection()->where($idCondition)->delete();
    }


}