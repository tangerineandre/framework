<?php
namespace Phidias\Component;

use \Phidias\Debug;

class Template implements TemplateInterface
{
    private $variables;
    private $URL;

    public function __construct($URL = NULL)
    {
        $this->variables = array();
        $this->URL       = $URL;
    }

    public function assign($variable, $value = NULL)
    {
        if (is_array($variable)) {
            $this->variables = array_merge($this->variables, $variable);
            return;
        }

        $this->variables[$variable] = $value;
    }

    public function isValid($template)
    {
        return is_file($template);
    }

    public function fetch($template)
    {
        /* Declare all variables in $this->variables as local PHP variables */
        foreach ($this->variables as $__php_tpl_var_name => $__php_tpl_var_value) {
            $$__php_tpl_var_name = $__php_tpl_var_value;
        }

        Debug::startBlock("including template '$template'", 'include');

        ob_start();
        include $template;
        $output = ob_get_contents();
        ob_end_clean();

        Debug::endBlock();

        return $output;
    }

    public function URL()
    {
        return $this->URL;
    }
}