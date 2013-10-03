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
    private $readDB;
    private $writeDB;

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
            $this->readDB   = $array['db'];
            $this->writeDB  = $array['db'];
        }

    }


    /* Temporarily */
    public static function sanitize($map)
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
}