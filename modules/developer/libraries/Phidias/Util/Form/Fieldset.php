<?php
namespace Phidias\Util\Form;

class Fieldset
{
    private $legend;
    private $fields;

    public function __construct($legend, $fields = array())
    {
        $this->legend = $legend;
        $this->fields = $fields;
    }

    public function toHTML()
    {
        $retval = "<fieldset>";
        $retval .= "<legend>$this->legend</legend>";
        foreach ($this->fields as $field) {
            $retval .= $field->toHTML();
        }
        $retval .= "</fieldset>";

        return $retval;
    }
}