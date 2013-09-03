<?php
namespace Phidias\Component\Form\Field\Element;

use Phidias\Component\Form\Field\Element;
use Phidias\Core\Language;

class RadioGroup extends Element
{
    public function toHTML()
    {
        $retval = "<ul class=\"radiogroup\">";
        foreach ($this->options as $value => $label) {

            $label      = Language::get($label);

            $selected   = $this->value !== NULL && $this->value == $value ? 'checked="checked"' : '';
            $id         = 'radio_'.$this->name.'_'.$value;

            $retval .= "<li>";
            $retval .= "<input id=\"$id\" type=\"radio\" name=\"$this->name\" value=\"$value\" $selected>";
            $retval .= "<label for=\"$id\">$label</label>";
            $retval .= "</li>";
        }
        $retval .= "</ul>";

        return $retval;
    }
}