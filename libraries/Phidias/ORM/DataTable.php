<?php
namespace Phidias\ORM;

class DataTable
{
    private $collection;
    private $columns;

    private $totalRecords       = 0;
    private $filteredRecords    = 0;
    private $ajaxSource         = NULL;

    public function __construct($collection)
    {
        $this->collection = $collection;
        $this->columns    = array();
    }

    public function setAjaxSource($source)
    {
        $this->ajaxSource = $source;
    }

    public function getAjaxSource()
    {
        return $this->ajaxSource;
    }

    public function getColumns()
    {
        return $this->columns;
    }

    public function column($title, $attribute, $filter = NULL)
    {
        $this->columns[] = new DataTable_Column($title, $attribute, $filter);
        return $this;
    }

    public function getTotalRecords()
    {
        return $this->totalRecords;
    }

    public function getFilteredRecords()
    {
        return $this->filteredRecords;
    }

    public function getData()
    {
        return $this->collection->find();
    }

    public function filter($options)
    {
        $class      = basename($this->collection->getClassName());
        $attributes = $this->collection->getAttributes(TRUE);

        $this->totalRecords     = $this->collection->count();
        $this->filteredRecords  = $this->totalRecords;

        /* incoming search criteria */
        $search = isset($options['sSearch']) ? trim($options['sSearch']) : NULL;
        if ($search) {
            $words = explode(' ', str_replace("'", "\'", $search));
            $conditions = array();
            foreach ($words as $word) {
                if (!$word = trim($word)) {
                    continue;
                }

                $matchesWordConditions = array();
                foreach ($this->columns as $column) {
                    if ($column->attribute !== NULL && $attributes[$column->attribute] === NULL) {
                        $matchesWordConditions[] = $class.'.'.$column->attribute." LIKE '$word%'";
                    }
                }
                $conditions[] = '('.implode(' OR ', $matchesWordConditions).')';
            }

            $this->collection->where(implode(' AND ', $conditions));
            $this->filteredRecords = $this->collection->count();
        }

        /* order criteria */
        $orders = array();

        if (isset($options['iSortCol_0']) && isset($this->columns[$options['iSortCol_0']])) {
            $sortColumn = $this->columns[$options['iSortCol_0']];
            $sortOrder  = isset($options['sSortDir_0']) ? $options['sSortDir_0'] : 'asc';
            if ( isset($sortColumn->attribute) ) {
                $sortAttribute = isset($attributes[$sortColumn->attribute]) ? $attributes[$sortColumn->attribute] : $class.'.'.$sortColumn->attribute;
                $orders[] = $sortAttribute.' '.$sortOrder;
            }
        }

        if (isset($options['iSortCol_1']) && isset($this->columns[$options['iSortCol_1']])) {
            $sortColumn = $this->columns[$options['iSortCol_1']];
            $sortOrder  = isset($options['sSortDir_1']) ? $options['sSortDir_1'] : 'asc';
            if ( isset($sortColumn->attribute) ) {
                $sortAttribute = isset($attributes[$sortColumn->attribute]) ? $attributes[$sortColumn->attribute] : $class.'.'.$sortColumn->attribute;
                $orders[] = $sortAttribute.' '.$sortOrder;
            }
        }

        if (isset($options['iSortCol_2']) && isset($this->columns[$options['iSortCol_2']])) {
            $sortColumn = $this->columns[$options['iSortCol_2']];
            $sortOrder  = isset($options['sSortDir_2']) ? $options['sSortDir_2'] : 'asc';
            if ( isset($sortColumn->attribute) ) {
                $sortAttribute = isset($attributes[$sortColumn->attribute]) ? $attributes[$sortColumn->attribute] : $class.'.'.$sortColumn->attribute;
                $orders[] = $sortAttribute.' '.$sortOrder;
            }
        }

        if ($orders) {
            $this->collection->orderBy(implode(', ', $orders));
        }

        /* limit */
        $start = isset($options['iDisplayStart'])   ? $options['iDisplayStart'] : 0;
        $limit = isset($options['iDisplayLength'])  ? $options['iDisplayLength'] : 10;
        $this->collection->limit("$start, $limit");
    }
}

class DataTable_Column
{
    public $title;
    public $isSortable;
    public $attribute;

    private $filter;

    public function __construct($title, $attribute = NULL, $filter = NULL)
    {
        $this->title        = $title;
        $this->attribute    = $attribute;
        $this->filter       = $filter;

        $this->isSortable   = isset($this->attribute);
    }

    public function getValue($record)
    {
        if ($this->filter) {
            $function = $this->filter;
            return $function($record);
        }

        if ($this->attribute && isset($record->{$this->attribute})) {
            return  $record->{$this->attribute};
        }
    }
}