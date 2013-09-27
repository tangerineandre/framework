<?php
namespace Phidias\DB;

/*
 *
 * Table definition.  Basically just a description of a table.
 * DB will use it to generate CREATE TABLE statements
 *
 *
 */

class Table
{
    private $name;
    private $columns;
    private $primaryKeys;
    private $foreignKeys;

    public function __construct($name)
    {
        $this->name         = $name;
        $this->columns      = array();
        $this->primaryKeys  = array();
        $this->foreignKeys  = array();
    }

    public function getName()
    {
        return $this->name;
    }

    public function getColumn($name)
    {
        return isset($this->columns[$name]) ? $this->columns[$name] : NULL;
    }

    public function getColumns()
    {
        return $this->columns;
    }

    public function getPrimaryKeys()
    {
        return $this->primaryKeys;
    }

    public function getForeignKeys()
    {
        return $this->foreignKeys;
    }


    /*
     * Create a new column
     *
     * @param columnData
     * name [required]
     * type [required]
     * length
     * acceptNull   [default = FALSE]
     * default
     * unsigned
     * autoIncrement
     *
     */
    public function addColumn($columnData)
    {
        if (!isset($columnData['name'])) {
            trigger_error("column name not specified", E_USER_ERROR);
            return FALSE;
        }

        if (!isset($columnData['type'])) {
            trigger_error("column type not specified", E_USER_ERROR);
            return FALSE;
        }


        $this->columns[$columnData['name']] = $columnData;
    }

    public function setPrimaryKey($columnNames)
    {
        if (!is_array($columnNames)) {
            $columnNames = array($columnNames);
        }

        foreach ($columnNames as $columnName) {
            if (!isset($this->columns[$columnName])) {
                trigger_error("cannot set primary key: column '$columnName' not found", E_USER_ERROR);
            }

            $this->primaryKeys[$columnName] = $this->columns[$columnName];
        }
    }


    public function setForeignKey($name, Table $foreignTable, $foreignColumnName, $onDelete = NULL, $onUpdate = NULL)
    {
        $foreignColumn = $foreignTable->getColumn($foreignColumnName);
        if ($foreignColumn == NULL) {
            trigger_error("cannot add foreign key: foreign table '$foreignTable->name' does not contain column '$foreignColumnName'", E_USER_ERROR);
        }

        if (!isset($this->columns[$name])) {
            $this->columns[$name] = $foreignColumn;
            $this->columns[$name]['name'] = $name;
            unset($this->columns[$name]['autoIncrement']);
        }

        $this->foreignKeys[$name] = array(
            'table'     => $foreignTable->name,
            'column'    => $foreignColumn,
            'onDelete'  => $onDelete,
            'onUpdate'  => $onUpdate
        );

    }

}