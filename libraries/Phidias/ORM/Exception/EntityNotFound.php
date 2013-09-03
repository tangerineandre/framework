<?php
namespace Phidias\ORM\Exception;

class EntityNotFound extends \Exception
{
    public function __construct($entity)
    {
        $this->message = "entity not found";
    }
}