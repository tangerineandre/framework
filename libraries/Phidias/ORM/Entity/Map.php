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
    private $triggers;
    private $indexes;

    public function __construct(array $mapData = NULL)
    {
        $this->db           = NULL;
        $this->table        = NULL;
        $this->keys         = array();
        $this->attributes   = array();
        $this->relations    = array();
        $this->triggers     = array();
        $this->indexes      = array();

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

        $className = is_object($entity) ? get_class($entity) : $entity;

        $retval = array();
        foreach ($this->relations as $relationName => $relationData) {
            if ($relationData['entity'] == $className) {
                $retval[$relationName] = $relationData;
            }
        }

        return $retval;
    }

    public function getTriggers()
    {
        return $this->triggers;
    }

    public function getIndexes()
    {
        return $this->indexes;
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

        /* Map triggers */
        $this->triggers['insert'] = isset($array['triggers']['insert']) ? $array['triggers']['insert'] : array();
        if (!isset($this->triggers['insert']['before'])) {
            $this->triggers['insert']['before'] = null;
        }
        if (!isset($this->triggers['insert']['after'])) {
            $this->triggers['insert']['after'] = null;
        }

        $this->triggers['update'] = isset($array['triggers']['update']) ? $array['triggers']['update'] : array();
        if (!isset($this->triggers['update']['before'])) {
            $this->triggers['update']['before'] = null;
        }
        if (!isset($this->triggers['update']['after'])) {
            $this->triggers['update']['after'] = null;
        }

        $this->triggers['delete'] = isset($array['triggers']['delete']) ? $array['triggers']['delete'] : array();
        if (!isset($this->triggers['delete']['before'])) {
            $this->triggers['delete']['before'] = null;
        }
        if (!isset($this->triggers['delete']['after'])) {
            $this->triggers['delete']['after'] = null;
        }

        if (isset($array['indexes'])) {
            $this->indexes = $array['indexes'];
        }

    }

}