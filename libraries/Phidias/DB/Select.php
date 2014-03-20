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
    private $useIndex;
    private $fields;
    private $conditions;
    private $joins;
    private $joinData;
    private $orderBy;
    private $limit;
    private $groupBy;
    private $having;

    public function __construct($table, $alias = NULL)
    {
        if ($alias == NULL) {
            $alias = $table;
        }

        $this->table        = $table;
        $this->alias        = $alias;

        $this->useIndex     = array();
        $this->fields       = array();
        $this->conditions   = array();
        $this->joins        = array();
        $this->joinData     = array();
        $this->orderBy      = array();
        $this->groupBy      = array();
    }

    public function field($fieldName = NULL, $origin = NULL)
    {
        /* Clear all fields */
        if ($fieldName === NULL) {
            $this->fields = array();

            /* ...recursively */
            foreach ($this->joins as $nestedData) {
                $nestedData['select']->field();
            }

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

    public function limit($offset, $value = NULL)
    {
        if ($value !== null) {
            $this->limit = "$offset, $value";
        } else {
            $this->limit = $offset;
        }

        return $this;
    }

    public function having($value)
    {
        $this->having = $value;

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

    public function useIndex($index)
    {
        $this->useIndex[] = $index;

        return $this;
    }

    public function getAlias()
    {
        return $this->alias;
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


    public function toSQL($count = FALSE)
    {
        $this->flatten();

        if ($count) {
            if ($this->groupBy) {
                $countField = "DISTINCT({$this->groupBy[0]})";
            } else {
                $countField = '*';
            }

            $sqlQuery = "SELECT COUNT($countField) as count "."\n";
        } else {
            $sqlQuery = "SELECT "."\n";
            $allColumns = array();
            foreach ($this->fields as $columnAlias => $columnSource) {
                $allColumns[] = $columnSource.' as `'.$columnAlias.'`';
            }
            $sqlQuery .= implode(', '."\n", $allColumns)." \n";
        }

        $sqlQuery .= "FROM "."\n";
        $sqlQuery .= $this->table.' `'.$this->alias."`\n";

        if ($this->useIndex) {
            $sqlQuery .= "USE INDEX(".implode(', ', $this->useIndex).") "."\n";
        }

        foreach ($this->joinData as $joinData) {
            $sqlQuery .= $joinData['type'].' JOIN '.$joinData['foreignTable'].' `'.$joinData['foreignAlias'].'` ON '.$joinData['joinCondition']." \n";
        }

        if ($this->conditions) {
            $sqlQuery .= 'WHERE ('.implode(') AND (', $this->conditions).") \n";
        }

        if ($this->groupBy && !$count) {
            $sqlQuery .= "GROUP BY ".implode(', ', $this->groupBy)." \n";
        }

        if ($this->having !== NULL) {
            $sqlQuery .= "HAVING $this->having"." \n";
        }

        if ($this->orderBy && !$count) {
            $orderBy = array();
            foreach ($this->orderBy as $fieldName) {
                $orderBy[] = isset($this->fields[$fieldName]) ? $this->fields[$fieldName] : $fieldName;
            }

            $sqlQuery .= "ORDER BY ".implode(', ', $orderBy)." \n";
        }
        
        if ($this->limit !== NULL && !$count) {
            $sqlQuery .= "LIMIT $this->limit"." \n";
        }

        return $sqlQuery;
    }

}