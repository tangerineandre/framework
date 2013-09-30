<?php
namespace Phidias\ORM\Exception;

class EntityNotFound extends \Exception
{
    public $primaryKeyValue;

    public function __construct($primaryKeyValue)
    {
        $this->primaryKeyValue = $primaryKeyValue;
        return parent::__construct("entity not found");
    }
}