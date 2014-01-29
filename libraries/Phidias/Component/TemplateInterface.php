<?php
namespace Phidias\Component;

interface TemplateInterface
{
    public function assign($name, $value = NULL); //assign($array) must assume that every key in the array is the variable name, and the array value the variable value
    public function isValid($file);
    public function fetch($file);
    public function URL();
}