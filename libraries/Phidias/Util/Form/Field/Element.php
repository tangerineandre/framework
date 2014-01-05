<?php
namespace Phidias\Util\Form\Field;

use Phidias\Core\Language;

class Element
{
    protected $type;
    protected $name;
    protected $options;
    protected $attributes;
    protected $value;
    protected $errors;

    public function __construct($type, $name, $options, $attributes)
    {
        $this->type         = strtolower($type);
        $this->name         = $name;
        $this->options      = $options;
        $this->attributes   = $attributes;
        $this->value        = NULL;
        $this->errors       = array();
    }

    public function setValue($value)
    {
        $this->value = $value;
    }
    
    public function setAttributes($attributes)
    {
        $this->attributes = $attributes;
    }

    public function isRequired()
    {
        return isset($this->attributes['required']) && $this->attributes['required'];
    }

    /* Function to be extended by each inheriting class.  Must populate $this->errors if applies */
    protected function validate($value)
    {
    }

    private function validateHTML5($value)
    {
        /* Check emptiness */
        if (trim($value) === '') {
            if (isset($this->attributes['required']) && $this->attributes['required']) {
                $this->addError("$this->name is required");
            }
            return;
        }

        /* Validate patterns */
        if (isset($this->attributes['pattern'])) {
            $pattern = '/^'.rtrim(ltrim($this->attributes['pattern'],'^'),'$').'$/';
            if (!preg_match($pattern, $value)) {
                $this->addError("$this->name is invalid");
            }
        }
    }

    public function hasErrors()
    {
        $this->errors = array();
        $this->validateHTML5($this->value);
        $this->validate($this->value);

        return !empty($this->errors);
    }

    protected function addError($message)
    {
        $this->errors[] = isset($this->attributes['message']) ? $this->attributes['message'] : $message;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    protected function getOption($option, $defaultValue = NULL)
    {
        return isset($this->options[$option]) ? $this->options[$option] : $defaultValue;
    }

    public function filter($input)
    {
        return $input;
    }

    public function toHTML()
    {
        $attributesHTML = $this->getAttributesHTML();
        return "<input type=\"$this->type\" name=\"$this->name\" value=\"$this->value\" $attributesHTML />";
    }

    protected function getAttributesHTML()
    {
        if ($this->attributes === NULL) {
            return NULL;
        }

        /* Hack.  Use "message" attribute as custom error message */
        if (isset($this->attributes['message'])) {
            $message = str_replace("'", "\'", Language::get($this->attributes['message']));
            $this->attributes['oninvalid']  = "setCustomValidity('$message')";
            $this->attributes['oninput']    = "setCustomValidity('')";
            unset($this->attributes['message']);
        }

        $retval = '';
        foreach ($this->attributes as $attributeName => $attributeValue) {
            $retval .= "$attributeName=\"$attributeValue\" ";
        }

        return $retval;
    }
}