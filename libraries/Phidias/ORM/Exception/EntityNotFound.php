<?php
namespace Phidias\ORM\Exception;

class EntityNotFound extends \Exception
{
    public $className;
    public $primaryKeyValue;

    public function __construct($className, $primaryKeyValue)
    {
        $this->className = $className;
        $this->primaryKeyValue = $primaryKeyValue;

        return parent::__construct("$className '$primaryKeyValue' not found");
    }
}