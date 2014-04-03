<?php
namespace Phidias\Util\Form\Field\Element;

use Phidias\Util\Form\Field\Element;

class Date extends Element
{
    public function toHTML()
    {
        $attributesHTML = $this->getAttributesHTML();
        $value = $this->value ? date("Y-m-d", $this->value) : '';
        return "<input type=\"date\" name=\"$this->name\" value=\"$value\" $attributesHTML />";
    }

    public function filter($input)
    {
        if (!$input) {
            return NULL;
        }

        list($y, $m, $d) = explode('-', $input);

        if (!isset($y) || !isset($m) || !isset($d)) {
            return NULL;
        }

        return mktime(0, 0, 0, $m, $d, $y);
    }
}