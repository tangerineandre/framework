<?php
namespace Phidias\Util\Form\Field\Element;

use Phidias\Util\Form\Field\Element;

class TextArea extends Element
{
    public function toHTML()
    {
        $attributesHTML = $this->getAttributesHTML();
        return "<textarea name=\"$this->name\" $attributesHTML>$this->value</textarea>";
    }
}