<?php
namespace Phidias\ORM\Dataset;

class Iterator implements \Iterator
{
    private $_dataset;
    private $_resultSet;
    private $_pointerStart;

    private $_restrictions;

    private $_keyAttributes;
    private $_lastSeenKeys;

    private $_pointer;
    private $_currentRow;

    public function __construct($dataset, $resultSet = NULL, $pointerStart = 0, $restrictions = NULL)
    {
        $this->_dataset         = $dataset;
        $this->_resultSet       = $resultSet;
        $this->_pointerStart    = $pointerStart;

        $this->_restrictions    = $restrictions;

        $this->_keyAttributes   = array();
        $this->_lastSeenKeys    = array();

        $schema = $this->_dataset->getSchema();
        $tableAlias = $this->_dataset->getTableAlias();
        foreach (array_keys($schema['keys']) as $attributeName) {
            $this->_keyAttributes[] = $tableAlias.'_'.$attributeName;
        }


        $this->_pointer         = NULL;
        $this->_currentRow      = NULL;
    }

    function rewind()
    {
        if ( $this->_resultSet === NULL ) {
            $this->_resultSet = $this->_dataset->getResultSet();
        }

        $this->_resultSet->data_seek($this->_pointerStart);
        $this->_pointer     = $this->_pointerStart;
        $this->_currentRow  = $this->_resultSet->fetch_assoc();

        foreach ( $this->_keyAttributes as $index => $attribute ) {
            $this->_lastSeenKeys[$index] = $this->_currentRow[$attribute];
        }
    }

    function valid()
    {
        /* no row preset */
        if ($this->_currentRow === NULL) {
            return FALSE;
        }

        /* Current record ID not found */
        foreach ($this->_keyAttributes as $attribute) {
            if ($this->_currentRow[$attribute] === NULL) {
                return FALSE;
            }
        }

        /* All restrictions validate */
        if ($this->_restrictions === NULL) {
            return TRUE;
        }

        foreach ( $this->_restrictions as $filter ) {
            if ( $this->_currentRow[$filter['column']] != $filter['value'] ) {
                return FALSE;
            }
        }

        return TRUE;
    }

    function current()
    {
        return $this->_dataset->toObject($this->_currentRow, $this->_pointer, $this->_resultSet);
    }

    function next()
    {
        //move forward until you get something different than the current row
        while ($this->_currentRow !== NULL && $this->_alreadySeen()) {
            $this->_resultSet->data_seek(++$this->_pointer);
            $this->_currentRow = $this->_resultSet->fetch_assoc();
        }

        if ($this->_currentRow === NULL) {
            $this->_lastSeenKeys = NULL;
        } else {
            foreach ( $this->_keyAttributes as $index => $attribute ) {
                $this->_lastSeenKeys[$index] = $this->_currentRow[$attribute];
            }
        }
    }

    private function _alreadySeen()
    {
        foreach ( $this->_keyAttributes as $index => $attribute ) {
            if ($this->_lastSeenKeys[$index] != $this->_currentRow[$attribute])  {
                return FALSE;
            }
        }

        return TRUE;
    }

    function key()
    {
        return $this->_pointer;
    }

    public function first()
    {
        $this->rewind();
        return $this->valid() ? $this->current() : NULL;
    }

    public function toJSON()
    {
        $retval = array();
        foreach ($this as $object) {
            $retval[] = $object->toJSON();
        }

        return '['.implode(',', $retval).']';
    }
}