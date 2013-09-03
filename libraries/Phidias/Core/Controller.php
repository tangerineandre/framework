<?php
namespace Phidias\Core;

class RequiredAttributeException extends \Exception {}

class Controller
{
    protected $attributes;
    protected $view;

    private $_variables;

    public function __construct($attributes = array())
    {
        $this->attributes   = new Controller_Attributes($attributes);
        $this->view         = new Controller_ViewData;
        $this->_variables   = array();
    }

    public function set($name, $value)
    {
        $this->_variables[$name] = $value;
    }

    public function get($name)
    {
        return isset($this->_variables[$name]) ? $this->_variables[$name] : NULL;
    }

    public function getVariables()
    {
        return $this->_variables;
    }

    public function getAttributes()
    {
        return $this->attributes;
    }

    public function useView($id)
    {
        $this->view->setTemplate($id);
    }

    public function disableView()
    {
        $this->useView(FALSE);
    }

    public function getViewData()
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


class Controller_ViewData
{
    private $template;

    public function setTemplate($id)
    {
        $this->template = $id;
    }

    public function getTemplate()
    {
        return $this->template;
    }
}