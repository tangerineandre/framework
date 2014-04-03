<?php
namespace Phidias\Util\Form\Field\Element;

use Phidias\Util\Form\Field\Element;

class Select extends Element
{
    public function toHTML()
    {
        $retval = "<select name=\"$this->name\">";
        foreach ($this->options as $value => $label) {
            $selected = $this->value == $value ? 'selected="selected"' : '';
            $retval .= "<option value=\"$value\" $selected>$label</option>";
        }
        $retval .= "</select>";

        return $retval;
    }
}