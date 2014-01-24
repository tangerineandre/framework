<?php
namespace Phidias\Component;

interface ViewInterface
{
    public function assign($name, $value = NULL);
    public function isValid($file);
    public function fetch($file);
}