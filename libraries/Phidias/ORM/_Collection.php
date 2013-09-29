<?php
namespace Phidias\ORM;

use Phidias\ORM\Dataset;

class Collection extends Dataset
{
    /* Unit of work */
    private $_unitOfWork;
    private $_maxFlushSize;
    private $_flushCount;

    public function __construct($className)
    {
        parent::__construct($className);
        $this->_returnType = Dataset::TYPE_COLLECTION;

        $this->_unitOfWork      = array();
        $this->_maxFlushSize    = 5000;
        $this->_flushCount      = 0;
    }


    /* Unit of work functions */
    public function add($entity)
    {
        $this->_unitOfWork[] = $entity;

        $unitSize = count($this->_unitOfWork);
        if ( $unitSize >= $this->_maxFlushSize ) {
            $this->save();
            $this->_flushCount += $unitSize;
        }
    }

    public function save()
    {
        $className          = $this->_className;
        $schema             = $this->getSchema();
        $table              = $schema['table'];
        $unitLength         = count($this->_unitOfWork);

        if ( !$unitLength ) {
            return $this->_flushCount;
        }

        $columns = array(); //column_name => objectAttribute
        foreach ( $schema['keys'] as $keyName => $keyData ) {
            $columns[isset($keyData['column']) ? $keyData['column'] : $keyName] = $keyName;
        }

        if ( isset($schema['attributes']) ) {
            foreach ( $schema['attributes'] as $attributeName => $attributeData ) {
                $columns[isset($attributeData['column']) ? $attributeData['column'] : $attributeName] = $attributeName;
            }
        }

        if ( isset($schema['relations']) ) {
            foreach ( $schema['relations'] as $relationName => $relationData ) {

                $columnName = isset($relationData['column']) ? $relationData['column'] : $relationName;
                if ( isset($columns[$columnName]) ) {
                    continue;
                }

                $columns[$columnName] = $relationName;
            }
        }

        $queryHead  = "INSERT INTO $table \n";
        $queryHead .= "(".implode(',', array_keys($columns)).") \n";
        $queryHead .= " VALUES \n";

        $queryValues = array();

        for ( $index = 0; $index < $unitLength; $index++ ) {
            $targetObject = $this->_unitOfWork[$index];
            $targetValues = array();
            foreach ( $columns as $columnName => $attributeName  ) {
                $targetValues[$columnName] = isset($targetObject->$attributeName) ? "'".DB::escapeString($targetObject->$attributeName)."'" : 'NULL';
            }

            $queryValues[] = "(".implode(',', $targetValues).") \n";
        }

        $query = $queryHead.implode(',', $queryValues);

        /* Run query */
        $className::getDB()->query($query);

        /* Clear unit */
        $this->clear();

        return $this->_flushCount + $unitLength;
    }

    public function clear()
    {
        $this->_unitOfWork = array();
    }



    /* Record manipulation functions */
    public function update()
    {
        $table  = $this->_schema['table'];
        $query  = "UPDATE $table $this->_tableAlias"."\n";

        $values = array();
        foreach($this->_attributes as $attributeName => $attributeSource) {
            $column             = $this->_tableAlias.'.'.(isset($this->_schema['attributes'][$attributeName]['column']) ? $this->_schema['attributes'][$attributeName]['column'] : $attributeName);
            $attributeSource    = $this->_translate($attributeSource);
            $values[]           = "$column = $attributeSource";
        }

        if (!$values) {
            return 0;
        }

        $query .= "SET ".implode(", "."\n", $values).' '."\n";

        if ($this->_where) {
            $query .= "WHERE ".$this->_translate(implode(' AND ', $this->_where))."\n";
        }

        $className  = $this->_className;
        $db         = $className::getDB();
        $db->query($query);

        return $db->affectedRows();
    }

    public function delete()
    {
        if (!$this->_where) {
            trigger_error("Ignoring Collection::delete() because it has no conditionals", E_USER_WARNING);
            return 0;
        }

        $table  = $this->_schema['table'];
        $query  = "DELETE FROM $table "."\n";
        $query .= "WHERE ".$this->_translate(implode(' AND ', $this->_where))."\n";

        $query = str_replace($this->_tableAlias.'.', '', $query);

        $className  = $this->_className;
        $db         = $className::getDB();
        $db->query($query);

        return $db->affectedRows();
    }

}