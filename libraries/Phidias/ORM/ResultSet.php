<?php
namespace Phidias\ORM;

use Phidias\DB\Select;

class ResultSet
{
    private $alias;
    private $map;

    private $attributes;
    private $joins;

    private $where;
    private $orderBy;
    private $groupBy;
    private $limit;

    public function __construct($alias, $map)
    {
        $this->alias        = $alias;
        $this->map          = $map;

        $this->attributes   = array();
        $this->joins        = array();

        $this->where        = array();
        $this->groupBy      = array();
        $this->orderBy      = array();
        $this->limit        = NULL;

        /* Always select keys */
        foreach ($this->map->getKeys() as $keyAttributeName) {
            $this->attr($keyAttributeName);
        }
    }

    public function attr($name, $origin = NULL)
    {
        $this->attributes["$this->alias.$name"] = $origin === NULL ? "$this->alias.$name" : $origin;
        return $this;
    }

    public function attrs()
    {
        foreach (func_get_args() as $attr) {
            $this->attr($attr);
        }

        return $this;
    }

    public function where($condition)
    {
        $this->where[] = $condition;
        return $this;
    }

    public function groupBy($group)
    {
        $this->groupBy[] = $group;
        return $this;
    }

    public function orderBy($order)
    {
        $this->orderBy[] = $order;
        return $this;
    }

    public function limit($limit)
    {
        $this->limit = $limit;
    }

    public function join($type, $resultSet, $relationIdentifier, $identifierIsLocal)
    {
        $relation = $identifierIsLocal ? $this->map->getRelation($relationIdentifier) : $resultSet->map->getRelation($relationIdentifier);
        if ($relation == NULL) {
            trigger_error("Relation '$relationIdentifier' not found", E_USER_ERROR);
        }

        if ($identifierIsLocal) {
            $condition  = '`'.$this->alias.'`.'.$relation['column'].' = `'.$resultSet->alias.'`.'.$resultSet->map->getAttribute($relation['attribute'])['column'];
        } else {
            $condition  = '`'.$this->alias.'`.'.$this->map->getAttribute($relation['attribute'])['column'].' = `'.$resultSet->alias.'`.'.$relation['column'];
        }

        $this->joins[] = array(
            'type'      => $type,
            'resultSet' => $resultSet,
            'condition' => $condition
        );

        return $this;
    }


    private function buildAliasMap()
    {
        $retval = array();
        foreach ($this->map->getAttributes() as $attributeName => $attributeData) {
            $retval["$this->alias.$attributeName"] = '`'.$this->alias.'`.`'.$attributeData['column'].'`';
        }

        foreach ($this->joins as $join) {
            $retval = array_merge($retval, $join['resultSet']->buildAliasMap());
        }

        return $retval;
    }


    private function translate($string, $aliasMap)
    {
        return $string === NULL ? NULL : str_replace(array_keys($aliasMap), $aliasMap, $string);
    }

    public function buildSelect($aliasMap = NULL)
    {
        if ($aliasMap == NULL) {
            $aliasMap = $this->buildAliasMap();
        }

        $select = new Select($this->map->getTable(), $this->alias);

        foreach ($this->attributes as $name => $origin) {
            $select->field($name, $this->translate($origin, $aliasMap));
        }

        foreach ($this->where as $condition) {
            $select->where($this->translate($condition, $aliasMap));
        }

        foreach ($this->groupBy as $group) {
            $select->groupBy($this->translate($group, $aliasMap));
        }

        foreach ($this->orderBy as $order) {
            $select->orderBy($this->translate($order, $aliasMap));
        }

        foreach ($this->joins as $join) {
            $select->join($join['type'], $join['resultSet']->buildSelect($aliasMap), $join['condition']);
        }

        return $select;
    }

}