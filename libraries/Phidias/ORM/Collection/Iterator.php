<?php
namespace Phidias\ORM\Collection;

class Iterator implements \Iterator
{
    private $collection;

    private $resultSet;
    private $pointerStart;

    private $restrictions;

    private $keyAttributes;
    private $lastSeenKeys;

    private $pointer;
    private $currentRow;

    public function __construct($collection, $resultSet = NULL, $pointerStart = 0, $restrictions = NULL)
    {
        $this->collection      = $collection;
        $this->resultSet       = $resultSet;
        $this->pointerStart    = $pointerStart;

        $this->restrictions    = $restrictions;

        $this->keyAttributes   = array();
        $this->lastSeenKeys    = array();

        $map    = $this->collection->getEntity()->getMap();
        $alias  = $this->collection->getAlias();
        foreach ($map['keys'] as $keyName) {
            $this->keyAttributes[] = $alias.'_'.$keyName;
        }

        $this->pointer         = NULL;
        $this->currentRow      = NULL;
    }

    function rewind()
    {
        if ( $this->resultSet === NULL ) {
            $this->resultSet = $this->collection->getResultSet();
        }

        $this->resultSet->data_seek($this->pointerStart);
        $this->pointer     = $this->pointerStart;
        $this->currentRow  = $this->resultSet->fetch_assoc();

        foreach ( $this->keyAttributes as $index => $attribute ) {
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
        foreach ($this->keyAttributes as $attribute) {
            if ($this->currentRow[$attribute] === NULL) {
                return FALSE;
            }
        }

        /* All restrictions validate */
        if ($this->restrictions === NULL) {
            return TRUE;
        }

        foreach ( $this->restrictions as $filter ) {
            if ( $this->currentRow[$filter['column']] != $filter['value'] ) {
                return FALSE;
            }
        }

        return TRUE;
    }

    function current()
    {
        return $this->collection->toObject($this->currentRow, $this->pointer, $this->resultSet);
    }

    function next()
    {
        //move forward until you get something different than the current row
        while ($this->currentRow !== NULL && $this->_alreadySeen()) {
            $this->resultSet->data_seek(++$this->pointer);
            $this->currentRow = $this->resultSet->fetch_assoc();
        }

        if ($this->currentRow === NULL) {
            $this->lastSeenKeys = NULL;
        } else {
            foreach ( $this->keyAttributes as $index => $attribute ) {
                $this->lastSeenKeys[$index] = $this->currentRow[$attribute];
            }
        }
    }

    private function _alreadySeen()
    {
        foreach ( $this->keyAttributes as $index => $attribute ) {
            if ($this->lastSeenKeys[$index] != $this->currentRow[$attribute])  {
                return FALSE;
            }
        }

        return TRUE;
    }

    function key()
    {
        return $this->pointer;
    }

    public function first()
    {
        $this->rewind();
        return $this->valid() ? $this->current() : NULL;
    }

    public function fetchAll()
    {
        $retval = array();
        foreach ($this as $object) {
            $retval[] = $object;
        }
        return $retval;
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