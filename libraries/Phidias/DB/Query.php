<?php
namespace Phidias\DB;

class Query
{
    private $_table;
    private $_tableAlias;

    private $_select;
    private $_conditions;
    private $_joins;
    private $_limit;
    private $_order;

    private static $_tableAliasRepository;

    public static function allocateAlias($table)
    {
        if (strpos($table,' ') !== FALSE) {
            $table = 'querytable';
        }

        if ( !isset(self::$_tableAliasRepository[$table]) ) {
            self::$_tableAliasRepository[$table] = array();
        }

        $alias = $table.count(self::$_tableAliasRepository[$table]);
        self::$_tableAliasRepository[$table][] = $alias;
        return $alias;
    }

    public function __construct($table, $alias)
    {
        $this->_table       = $table;
        $this->_tableAlias  = $alias;

        $this->_select      = array();
        $this->_conditions  = array();
        $this->_joins       = array();
    }

    public function getTable()
    {
        return $this->_table;
    }

    public function getConditions()
    {
        return $this->_conditions;
    }

    public function clearSelect()
    {
        $this->_select = array();
    }

    public function selectColumn($columnAlias, $columnSource)
    {
        $this->_select[$columnAlias] = $columnSource;
    }

    public function addCondition($condition)
    {
        $this->_conditions[] = $condition;
    }

    public function leftJoin($joinTable, $joinAlias, $keyCondition, $joinConditions = array())
    {
        $this->_joins[] = array(
            'type'          => 'LEFT',
            'table'         => $joinTable,
            'alias'         => $joinAlias,
            'keyCondition'  => $keyCondition,
            'conditions'    => $joinConditions
        );
    }

    public function limit($value)
    {
        $this->_limit = $value;
    }

    public function orderBy($value)
    {
        $this->_order = $value;
    }

    public function orderAdd($value)
    {
        if ( $this->_order === NULL ) {
            $this->_order = $value;
        } else if ($value) {
            $this->_order .= ",".$value;
        }
    }

    public function merge($query)
    {
        $this->_select  = array_merge($this->_select, $query->_select);
        $this->_joins   = array_merge($this->_joins, $query->_joins);
    }


    public function toSQL()
    {
        $sqlQuery = "SELECT"."\n";
        $allColumns = array();
        foreach ( $this->_select as $columnAlias => $columnSource ) {
            $allColumns[] = $columnSource.' as '.$columnAlias;
        }
        $sqlQuery .= implode(', ', $allColumns)."\n";

        $sqlQuery .= "FROM"."\n";
        $sqlQuery .= $this->_table.' '.$this->_tableAlias."\n";

        foreach ( $this->_joins as $joinData ) {
            $joinCondition = count($joinData['conditions']) ? (' AND '.implode(' AND ',$joinData['conditions'])) : '';
            $sqlQuery .= $joinData['type'].' JOIN '.$joinData['table'].' '.$joinData['alias'].' ON '.$joinData['keyCondition'].$joinCondition."\n";
        }

        if ( $this->_conditions ) {
            $sqlQuery .= 'WHERE '.implode(' AND ', $this->_conditions)."\n";
        }

        if ( $this->_order !== NULL ) {
            $sqlQuery .= "ORDER BY $this->_order"."\n";
        }

        if ( $this->_limit !== NULL ) {
            $sqlQuery .= "LIMIT $this->_limit"."\n";
        }

        return $sqlQuery;
    }
}