<?php
namespace Phidias\Component;

interface ControllerInterface
{
    public function assign($name, $value = NULL);
    public function isValid($file);
    public function fetch($file);
}