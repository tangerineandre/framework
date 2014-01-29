<?php
namespace Phidias\Util\Form;

use Phidias\Language;

class Field
{
    protected $name;
    protected $label;
    protected $element;
    protected $description;

    public static function factory($type, $name, $label = NULL, $options = NULL, $attributes = NULL, $description = NULL)
    {
        $elementClassName   = 'Phidias\Util\Form\Field\Element\\'.$type;
        if (!class_exists($elementClassName)) {
            $elementClassName = 'Phidias\Util\Form\Field\Element';
        }

        $elementObject  = new $elementClassName($type, $name, $options, $attributes);
        $fieldObject    = new Field($name, $label, $elementObject, $description);

        return $fieldObject;
    }

    public static function __callStatic($type, $arguments)
    {
        $name           = $arguments[0];
        $label          = isset($arguments[1]) ? $arguments[1] : NULL;
        $options        = isset($arguments[2]) ? $arguments[2] : NULL;
        $attributes     = isset($arguments[3]) ? $arguments[3] : NULL;
        $description    = isset($arguments[4]) ? $arguments[4] : NULL;

        return self::factory($type, $name, $label, $options, $attributes, $description);
    }

    public function __construct($name, $label, $element, $description)
    {
        $this->name         = $name;
        $this->label        = Language::get($label);
        $this->element      = $element;
        $this->description  = Language::get($description);
    }

    public function getName()
    {
        return $this->name;
    }

    public function setValue($value)
    {
        $this->element->setValue($value);
    }

    public function setAttributes($attributes)
    {
        $this->element->setAttributes($attributes);
    }

    public function hasErrors()
    {
        return $this->element->hasErrors();
    }

    public function filter($input)
    {
        return $this->element->filter($input);
    }

    public function toHTML()
    {
        $errors         = $this->element->getErrors();
        $noticeClass    = $errors ? "noticed" : '';
        $requiredClass  = $this->element->isRequired() ? 'required' : '';

        $retval = "<div class=\"field $noticeClass $requiredClass\">"."\n";
        $retval .= $this->label ? "    <label>".Language::get($this->label)."</label>"."\n" : '';
        $retval .= $this->element->toHTML()."\n";
        $retval .= $this->description ? "    <span class=\"description\">".Language::get($this->description)."</span>"."\n" : '';

        foreach ($errors as $notice) {
            $notice = Language::get($notice);
            $retval .= "<span class=\"notice\">$notice</span>"."\n";
        }

        $retval .= "</div>"."\n";
        return $retval;
    }

}