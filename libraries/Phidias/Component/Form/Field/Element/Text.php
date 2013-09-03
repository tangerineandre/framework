<?php
namespace Phidias\Component\Form\Field\Element;

use Phidias\Component\Form\Field\Element;

class Text extends Element
{
    public function toHTML()
    {
        $attributesHTML = $this->getAttributesHTML();
        return "<input type=\"text\" name=\"$this->name\" value=\"$this->value\" $attributesHTML />";
    }
}