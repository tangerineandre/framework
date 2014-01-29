<?php
namespace Phidias\Resource;


class Attributes
{
    private $data;

    public function __construct($data = array())
    {
        $this->data = $data;
    }

    public function get($name = NULL, $default_value = NULL)
    {
        if ( $name === NULL ) {
            return $this->data;
        }

        return isset($this->data[$name]) ? $this->data[$name] : $default_value;
    }

    public function required($name)
    {
        if ( !isset($this->data[$name]) ) {
            throw new Exception\RequiredAttribute(array('attribute' => $name));
        }

        return $this->data[$name];
    }
}
