<?php
namespace Phidias\Component;

class Form
{
    public $method;
    public $action;
    public $fields;
    private $index;
    private $values;

    public function __construct($action = NULL, $method = 'post')
    {
        $this->method   = $method;
        $this->action   = $action;
        $this->fields   = array();
        $this->index    = array();
        $this->values   = array();
    }

    public function action($action)
    {
        $this->action = $action;
    }

    public function field($field)
    {
        $fieldName = $field->getName();
        $this->index[$fieldName] = $field;
        $this->fields[] = $this->index[$fieldName];

        return $this;
    }

    public function fieldset($legend, $fields)
    {
        $fieldSet = array();

        foreach ($fields as $field) {
            $fieldName = $field->getName();
            $this->index[$fieldName] = $field;
            $fieldSet[] = $this->index[$fieldName];
        }

        $this->fields[] = array(
            'legend' => $legend,
            'fields' => $fieldSet
        );

        return $this;
    }

    public function setValues($values)
    {
        foreach ($values as $fieldName => $fieldValue) {
            $this->setValue($fieldName, $fieldValue);
        }
    }

    public function setValue($fieldName, $value)
    {
        if (!isset($this->index[$fieldName])) {
            return NULL;
        }

        $this->index[$fieldName]->setValue($value);
        $this->values[$fieldName] = $value;
    }

    public function values()
    {
        return $this->values;
    }

    public function setAttributes($attributesArray)
    {
        foreach ($attributesArray as $fieldName => $attributes) {
            if (!isset($this->index[$fieldName])) {
                continue;
            }

            $this->index[$fieldName]->setAttributes($attributes);
        }
    }

    /* Parse incoming data */
    public function fetch()
    {
        $data = HTTP\Request::POST();

        if (!$data) {
            $this->isValid();
            return FALSE;
        }

        foreach ($this->index as $fieldName => $fieldObject) {
            $incomingValue = $fieldObject->filter(isset($data[$fieldName]) ? $data[$fieldName] : NULL);
            $this->setValue($fieldName, $incomingValue);
        }

        return $this->isValid();
    }

    public function isValid()
    {
        $isValid = TRUE;

        foreach ($this->values as $fieldName => $value) {
            if (!isset($this->index[$fieldName])) {
                continue;
            }

            if ($this->index[$fieldName]->hasErrors()) {
                $isValid = FALSE;
            }
        }

        return $isValid;
    }

}