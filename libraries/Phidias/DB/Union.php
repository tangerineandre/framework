<?php
namespace Phidias\DB;

class Union
{
    private $selects;
    private $orderBy;
    private $limit;

    public function __construct()
    {
        $this->selects      = array();
        $this->orderBy      = array();
    }

    public function addSelect(Select $select)
    {
        $this->selects[] = $select;

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

    public function toSQL()
    {
        $queries = array();
        foreach ($this->selects as $select) {
            $queries[] = $select->toSQL();
        }

        $sqlQuery = "(".implode(") UNION (", $queries).")\n\n";

        if ($this->orderBy) {
            $sqlQuery .= "ORDER BY ".implode(', ', $this->orderBy)."\n";
        }

        if ($this->limit !== NULL) {
            $sqlQuery .= "LIMIT $this->limit"."\n";
        }

        return $sqlQuery;
    }

}