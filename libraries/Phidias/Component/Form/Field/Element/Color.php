<?php
namespace Phidias\Component\Form\Field\Element;

use Phidias\Component\Form\Field\Element;

class Color extends Element
{
    public function toHTML()
    {
        $attributesHTML = $this->getAttributesHTML();
        return "<input type=\"color\" name=\"$this->name\" value=\"$this->value\" $attributesHTML />";
    }

    public function validate($value)
    {
        if (!preg_match('/^#?([a-fA-F0-9]{6}|[a-fA-F0-9]{3})$/', $value)) {
            $this->addError('invalid color format');
        }
    }
}