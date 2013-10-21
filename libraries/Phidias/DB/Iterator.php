<?php
namespace Phidias\DB;

class Iterator implements \Iterator
{
    private $resultSet;
    private $className;
    private $assertions;
    private $pointerStart;

    private $key;
    private $attributes;
    private $nestedIterators;

    private $lastSeenKeys;
    private $pointer;
    private $currentRow;

    private $fetchFirstRow;

    public function __construct($className, $key, $fetchFirstRow = FALSE)
    {
        $this->resultSet        = NULL;
        $this->className        = $className;
        $this->assertions       = array();
        $this->pointerStart     = 0;

        $this->key              = (array)$key;
        $this->attributes       = array();
        $this->nestedIterators  = array();

        $this->lastSeenKeys     = array();
        $this->pointer          = NULL;
        $this->currentRow       = NULL;

        $this->fetchFirstRow    = $fetchFirstRow;
    }

    public function setResultSet($resultSet)
    {
        $this->resultSet = $resultSet;
        foreach ($this->nestedIterators as $nestedIterator) {
            $nestedIterator->setResultSet($resultSet);
        }

        return $this;
    }

    public function attr($name, $origin)
    {
        if ($origin instanceof Iterator) {
            $this->nestedIterators[$name] = $origin;
        } else {
            $this->attributes[$name] = $origin;
        }

        return $this;
    }

    private function setPointerStart($pointerStart)
    {
        $this->pointerStart = $pointerStart;
    }

    private function setAssertions($assertions)
    {
        $this->assertions = $assertions;
    }

    function current()
    {
        $id         = array();
        $assertions = array();
        foreach ($this->key as $keyFieldName) {
            $id[]                       = $this->currentRow[$keyFieldName];
            $assertions[$keyFieldName]  = $this->currentRow[$keyFieldName];
        }

        /* Create the new Entity */
        $className      = $this->className;
        $returnObject   = new $className($id, FALSE);

        foreach ($this->attributes as $attributeName => $sourceField) {
            $returnObject->$attributeName = isset($this->currentRow[$sourceField]) ? $this->currentRow[$sourceField] : NULL;
        }

        foreach ($this->nestedIterators as $attributeName => $nestedIterator) {

            $nestedIterator->setPointerStart($this->pointer);
            $nestedIterator->setAssertions($assertions);

            $returnObject->$attributeName = $nestedIterator->fetchFirstRow ? $nestedIterator->first() : $nestedIterator;
        }

        return $returnObject;
    }



    function rewind()
    {
        $this->resultSet->data_seek($this->pointerStart);
        $this->pointer     = $this->pointerStart;
        $this->currentRow  = $this->resultSet->fetch_assoc();

        foreach ($this->key as $index => $attribute) {
            $this->lastSeenKeys[$index] = $this->currentRow[$attribute];
        }
    }

    function valid()
    {
        /* no row preset */
        if ($this->currentRow === NULL) {
            return FALSE;
        }

        /* Current record ID not found */
        foreach ($this->key as $keyField) {
            if ($this->currentRow[$keyField] === NULL) {
                return FALSE;
            }
        }

        /* All assertions validate */
        if ($this->assertions === NULL) {
            return TRUE;
        }

        foreach ( $this->assertions as $columnName => $expectedValue ) {
            if ( $this->currentRow[$columnName] != $expectedValue ) {
                return FALSE;
            }
        }

        return TRUE;
    }

    function next()
    {
        //move forward until you get something different than the current row
        while ($this->currentRow !== NULL && $this->alreadySeen()) {
            $this->resultSet->data_seek(++$this->pointer);
            $this->currentRow = $this->resultSet->fetch_assoc();
        }

        if ($this->currentRow === NULL) {
            $this->lastSeenKeys = NULL;
        } else {
            foreach ($this->key as $index => $attribute) {
                $this->lastSeenKeys[$index] = $this->currentRow[$attribute];
            }
        }
    }

    function key()
    {
        return $this->pointer;
    }


    private function alreadySeen()
    {
        foreach ($this->key as $index => $attribute) {
            if ($this->lastSeenKeys[$index] != $this->currentRow[$attribute])  {
                return FALSE;
            }
        }

        return TRUE;
    }

    public function first()
    {
        $this->rewind();
        return $this->valid() ? $this->current() : NULL;
    }

    public function fetchAll()
    {
        $nested = array_keys($this->nestedIterators);

        $retval = array();
        foreach ($this as $object) {
            foreach ($nested as $attributeName) {
                if (is_a($object->$attributeName, 'Iterator')) {
                    $object->$attributeName = $object->$attributeName->fetchAll();
                }
            }
            $retval[] = $object;
        }
        return $retval;
    }

    public function toArray()
    {
        return (array)$this->fetchAll();
    }

    public function toJSON()
    {
        return json_encode($this->fetchAll());
    }
}