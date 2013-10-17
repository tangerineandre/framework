<?php
namespace Phidias\DB;

/*
 *
 * a SELECT query builder
 *
 * It requires ALL tables to have an alias
 *
 * A SELECTION (or "select") will return a group of FIELDS.
 * Every FIELD has a NAME and ORIGIN.  When no ORIGIN is specified, it is assumed to be a column with the given name
 *
 *
 * $select = Select('people');
 * $select->field('id');
 * $select->field('firstName');
 * $select->field('lastName');
 * $select->field('fullName', 'CONCAT(people.firstName, " ", people.lastName)');
 *
 * EVERY field referenced in the origin MUST include the table alias
 *
 * Alternative syntax:
 * $select->fields('id', 'firstName', 'lastName');
 *
 *
 */
class Select
{
    private $_table;
    private $_tableAlias;

    private $_fields;
    private $_conditions;
    private $_joins;
    private $_orders;
    private $_limit;

    public function __construct($table, $alias = NULL)
    {
        if ($alias == NULL) {
            $alias = $table;
        }

        $this->_table       = $table;
        $this->_tableAlias  = $alias;

        $this->_fields      = array();
        $this->_conditions  = array();
        $this->_joins       = array();
        $this->_orders      = array();
    }

    public function field($fieldName = NULL, $origin = NULL)
    {
        if ($fieldName === NULL) {
            $this->_fields = array();
            return $this;
        }

        if ($origin === NULL) {
            $origin = $this->_tableAlias.'.'.$fieldName;
        }

        $this->_fields[$fieldName] = $origin;
        return $this;
    }

    public function where($condition)
    {
        $this->_conditions[] = $condition;

        return $this;
    }

    public function join($type, $foreignTable, $foreignTableAlias, $foreignColumn, $localColumn, $joinCondition = NULL)
    {
        $this->_joins[] = array(
            'type'              => $type,
            'foreignTable'      => $foreignTable,
            'foreignTableAlias' => $foreignTableAlias,
            'foreignColumn'     => $foreignColumn,
            'localTableAlias'   => $this->_tableAlias,
            'localColumn'       => $localColumn,
            'joinCondition'     => $joinCondition
        );

        return $this;
    }

    public function leftJoin($foreignTable, $foreignTableAlias, $foreignColumn, $localColumn, $joinCondition = NULL)
    {
        $this->_joins[] = array(
            'type'              => 'LEFT',
            'foreignTable'      => $foreignTable,
            'foreignTableAlias' => $foreignTableAlias,
            'foreignColumn'     => $foreignColumn,
            'localTableAlias'   => $this->_tableAlias,
            'localColumn'       => $localColumn,
            'joinCondition'     => $joinCondition
        );

        return $this;
    }

    public function limit($value)
    {
        $this->_limit = $value;

        return $this;
    }

    public function orderBy($value = NULL)
    {
        if ($value === NULL) {
            $this->_orders = array();
        } else {
            $this->_orders[] = $value;
        }

        return $this;
    }

    public function merge($select)
    {
        $this->_fields  = array_merge($this->_fields, $select->_fields);
        $this->_joins   = array_merge($this->_joins, $select->_joins);
        $this->_orders  = array_merge($this->_orders, $select->_orders);
    }

    public function toSQL()
    {
        $sqlQuery = "SELECT"."\n";
        $allColumns = array();
        foreach ( $this->_fields as $columnAlias => $columnSource ) {
            $allColumns[] = $columnSource.' as '.$columnAlias;
        }
        $sqlQuery .= implode(', ', $allColumns)."\n";

        $sqlQuery .= "FROM"."\n";
        $sqlQuery .= $this->_table.' '.$this->_tableAlias."\n";

        foreach ( $this->_joins as $joinData ) {
            $keyCondition   = $joinData['foreignTableAlias'].'.'.$joinData['foreignColumn'].' = '.$joinData['localTableAlias'].'.'.$joinData['localColumn'];
            $joinCondition = isset($joinData['joinCondition']) ? ' AND '.$joinData['joinCondition'] : '';
            $sqlQuery .= $joinData['type'].' JOIN '.$joinData['foreignTable'].' '.$joinData['foreignTableAlias'].' ON '.$keyCondition.$joinCondition."\n";
        }

        if ( $this->_conditions ) {
            $sqlQuery .= 'WHERE '.implode(' AND ', $this->_conditions)."\n";
        }

        if ( $this->_orders ) {
            $sqlQuery .= "ORDER BY ".implode(', ', $this->_orders)."\n";
        }

        if ( $this->_limit !== NULL ) {
            $sqlQuery .= "LIMIT $this->_limit"."\n";
        }

        return $sqlQuery;
    }

}