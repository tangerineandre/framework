<?php
namespace Phidias\Component\Form\Field\Element;

use Phidias\Component\Form\Field\Element;

class Email extends Element
{
    public function toHTML()
    {
        $attributesHTML = $this->getAttributesHTML();
        return "<input type=\"email\" name=\"$this->name\" value=\"$this->value\" $attributesHTML />";
    }

    protected function validate($value)
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError("invalid email");
        }
    }
}