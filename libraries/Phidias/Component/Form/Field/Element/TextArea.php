<?php
namespace Phidias\Component\Form\Field\Element;

use Phidias\Component\Form\Field\Element;

class TextArea extends Element
{
    public function toHTML()
    {
        $attributesHTML = $this->getAttributesHTML();
        return "<textarea name=\"$this->name\" $attributesHTML>$this->value</textarea>";
    }
}