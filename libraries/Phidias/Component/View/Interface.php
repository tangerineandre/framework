<?php
namespace Phidias\Component;

interface View_Interface
{
    public function assign($name, $value = NULL);
    public function isValid($file);
    public function fetch($file);
}