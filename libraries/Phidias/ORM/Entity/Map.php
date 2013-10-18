<?php
namespace Phidias\ORM\Entity;

/*
 * An Entity map specifies how an entities
 * is mapped onto the database.
 *
 * It specifies the database and table which will hold
 * the entity, along with it's matching column types and relations
 *
 */
class Map
{
    private $db;

    private $table;
    private $keys;
    private $attributes;
    private $relations;

    public function __construct(array $mapData = NULL)
    {
        $this->readDB       = NULL;
        $this->writeDB      = NULL;
        $this->table        = NULL;
        $this->keys         = array();
        $this->attributes   = array();
        $this->relations    = array();

        if ($mapData !== NULL) {
            $this->fromArray($mapData);
        }
    }

    public function getTable()
    {
        return $this->table;
    }

    public function getDB()
    {
        return $this->db;
    }

    public function getKeys()
    {
        return $this->keys;
    }

    public function hasAttribute($attributeName)
    {
        return isset($this->attributes[$attributeName]);
    }

    public function getAttributes()
    {
        return $this->attributes;
    }

    public function getAttribute($attributeName)
    {
        return isset($this->attributes[$attributeName]) ? $this->attributes[$attributeName] : NULL;
    }

    public function getColumn($attributeName)
    {
        return isset($this->attributes[$attributeName]) ? $this->attributes[$attributeName]['column'] : NULL;
    }

    public function isAutoIncrement($attributeName)
    {
        return isset($this->attributes[$attributeName]['autoIncrement']) ? $this->attributes[$attributeName]['autoIncrement'] : FALSE;
    }


    public function hasRelation($relationName)
    {
        return isset($this->relations[$relationName]);
    }

    public function getRelation($relationName)
    {
        return $this->hasRelation($relationName) ? $this->relations[$relationName] : NULL;
    }

    public function getRelations($entity = NULL)
    {
        if ($entity === NULL) {
            return $this->relations;
        }

        $className = get_class($entity);

        $retval = array();
        foreach ($this->relations as $relationName => $relationData) {
            if ($relationData['entity'] == $className) {
                $retval[$relationName] = $relationData;
            }
        }
        return $retval;
    }


    private function fromArray($array)
    {
        if (!isset($array['attributes'])) {
            trigger_error('invalid map: no attributes defined', E_USER_ERROR);
        }

        if (!isset($array['keys'])) {
            trigger_error('invalid map: no keys defined', E_USER_ERROR);
        }

        foreach ($array['keys'] as $keyName) {
            if (!isset($array['attributes'][$keyName])) {
                trigger_error("invalid map: key '$keyName' is not defined as attribute", E_USER_ERROR);
            }
        }

        foreach ($array['attributes'] as $attributeName => &$attributeData) {
            if (!isset($attributeData['column'])) {
                $attributeData['column'] = $attributeName;
            }

            $this->attributes[$attributeName] = $attributeData;

            if (isset($attributeData['entity'])) {

                if (!class_exists($attributeData['entity'])) {
                    trigger_error("invalid map: related entity '{$attributeData['entity']}' not found", E_USER_ERROR);
                }

                if (!isset($attributeData['attribute'])) {
                    trigger_error("invalid map: no attribute specified for relation '$attributeName'", E_USER_ERROR);
                }

                $this->relations[$attributeName] = $attributeData;
            }
        }

        if (isset($array['db'])) {
            $this->db = $array['db'];
        }

        if (isset($array['table'])) {
            $this->table = $array['table'];
        }

        $this->keys = isset($array['keys']) ? $array['keys'] : array();

    }

}