<?php
namespace Phidias\Component;

use \Phidias\Core\Debug;

class View implements View_Interface
{
    private $_variables;
    private $_URL;

    private $attributes;

    public function __construct($URL = NULL)
    {
        $this->_variables   = array();
        $this->_URL         = $URL;

        $this->attributes   = NULL;
    }

    public function assign($variable, $value = NULL)
    {
        if ( is_array($variable) ) {
            $this->_variables = array_merge($this->_variables, $variable);
            return;
        }

        $this->_variables[$variable] = $value;
    }

    public function setAttributes($attributes)
    {
        $this->attributes = $attributes;
    }

    public function isValid($template)
    {
        return is_file($template);
    }

    public function fetch($template)
    {
        foreach ( $this->_variables as $__php_tpl_name => $__php_tpl_value ) {
            $$__php_tpl_name = $__php_tpl_value;
        }

        Debug::startBlock("including template '$template'", 'include');
        ob_start();
        include $template;
        $output = ob_get_contents();
        ob_end_clean();
        Debug::endBlock();

        return $output;
    }


    /* Functions to be used within the view */
    private function URL()
    {
        return $this->_URL;
    }
}