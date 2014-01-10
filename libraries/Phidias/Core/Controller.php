<?php
namespace Phidias\Core;

class RequiredAttributeException extends \Exception {}

class Controller
{
    private $view;
    private $model;

    protected $attributes;

    public function __construct($attributes = array())
    {
        $this->view         = NULL;
        $this->model        = array();

        $this->attributes   = new Controller_Attributes($attributes);
    }

    public function set($name, $value)
    {
        $this->model[$name] = $value;
    }

    public function get($name)
    {
        return isset($this->model[$name]) ? $this->model[$name] : NULL;
    }

    public function getVariables()
    {
        return $this->model;
    }

    public function getAttributes()
    {
        return $this->attributes;
    }

    public function useView($view)
    {
        $this->view = $view;
    }

    public function disableView()
    {
        $this->useView(FALSE);
    }

    public function getView()
    {
        return $this->view;
    }
}

class Controller_Attributes
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
            throw new RequiredAttributeException("'$name' is required");
        }

        return $this->data[$name];
    }
}
