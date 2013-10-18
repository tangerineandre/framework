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
    private $table;
    private $alias;

    private $fields;
    private $conditions;
    private $joins;
    private $joinData;
    private $orderBy;
    private $limit;
    private $groupBy;

    public function __construct($table, $alias = NULL)
    {
        if ($alias == NULL) {
            $alias = $table;
        }

        $this->table        = $table;
        $this->alias        = $alias;

        $this->fields       = array();
        $this->conditions   = array();
        $this->joins        = array();
        $this->joinData     = array();
        $this->orderBy      = array();
        $this->groupBy      = array();
    }

    public function field($fieldName = NULL, $origin = NULL)
    {
        if ($fieldName === NULL) {
            $this->fields = array();
            return $this;
        }

        if ($origin === NULL) {
            $origin = $this->alias.'.'.$fieldName;
        }

        $this->fields[$fieldName] = $origin;

        return $this;
    }

    public function where($condition)
    {
        $this->conditions[] = $condition;

        return $this;
    }

    public function join($type, $select, $conditions)
    {
        $this->joins[] = array(
            'type'          => $type,
            'select'        => $select,
            'conditions'    => (array)$conditions
        );

        return $this;
    }

    public function limit($value)
    {
        $this->limit = $value;

        return $this;
    }

    public function orderBy($value = NULL)
    {
        if ($value === NULL) {
            $this->orderBy = array();
        } else {
            $this->orderBy[] = $value;
        }

        return $this;
    }

    public function groupBy($value)
    {
        $this->groupBy[] = $value;

        return $this;
    }


    private function flatten()
    {
        foreach ($this->joins as $nestedData) {

            $select = $nestedData['select'];
            $select->flatten();

            $this->joinData[] = array(
                'type'          => strtoupper($nestedData['type']),
                'foreignTable'  => $select->table,
                'foreignAlias'  => $select->alias,
                'joinCondition' => implode(' AND ', $nestedData['conditions'])
            );

            $this->fields       = array_merge($this->fields, $select->fields);
            $this->joinData     = array_merge($this->joinData, $select->joinData);
            $this->conditions   = array_merge($this->conditions, $select->conditions);
        }
    }


    public function toSQL()
    {
        $this->flatten();

        $sqlQuery = "SELECT"."\n";
        $allColumns = array();
        foreach ($this->fields as $columnAlias => $columnSource) {
            $allColumns[] = $columnSource.' as `'.$columnAlias.'`';
        }
        $sqlQuery .= implode(', ', $allColumns)."\n";

        $sqlQuery .= "FROM"."\n";
        $sqlQuery .= $this->table.' `'.$this->alias."`\n";

        foreach ($this->joinData as $joinData) {
            $sqlQuery .= $joinData['type'].' JOIN '.$joinData['foreignTable'].' `'.$joinData['foreignAlias'].'` ON '.$joinData['joinCondition']."\n";
        }

        if ($this->conditions) {
            $sqlQuery .= 'WHERE '.implode(' AND ', $this->conditions)."\n";
        }

        if ($this->groupBy) {
            $groupBy = array();
            foreach ($this->groupBy as $fieldName) {
                if (!isset($this->fields[$fieldName])) {
                    trigger_error("groupBy($fieldName): no such field");
                    continue;
                }
                $groupBy[] = $this->fields[$fieldName];
            }

            $sqlQuery .= "GROUP BY ".implode(', ', $groupBy)."\n";
        }

        if ($this->orderBy) {
            $orderBy = array();
            foreach ($this->orderBy as $fieldName) {
                $orderBy[] = isset($this->fields[$fieldName]) ? $this->fields[$fieldName] : $fieldName;
            }

            $sqlQuery .= "ORDER BY ".implode(', ', $orderBy)."\n";
        }

        if ($this->limit !== NULL) {
            $sqlQuery .= "LIMIT $this->limit"."\n";
        }

        return $sqlQuery;
    }

}