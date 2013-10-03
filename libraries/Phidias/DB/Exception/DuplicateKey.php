<?php
namespace Phidias\DB\Exception;
use Phidias\DB\Exception;

class DuplicateKey extends Exception
{
    private $entry;
    private $key;

    public function getEntry()
    {
        return $this->entry;
    }

    public function getKey()
    {
        return $this->key;
    }

    public function __construct($error, $errno) {
        $matches = array();
        preg_match_all("/Duplicate entry '(.+)' for key '(.+)'/", $error, $matches);
        $this->entry    = isset($matches[1][0]) ? $matches[1][0] : NULL;
        $this->key      = isset($matches[2][0]) ? $matches[2][0] : NULL;

        return parent::__construct($error, $errno);
    }
}