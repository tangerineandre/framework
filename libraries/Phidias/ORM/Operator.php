<?php
namespace Phidias\ORM;

class Operator
{
    private static $operators = array('&gt', '&gte', '&lt', '&lte', '&in', '&nin', '&ne', '&eq', '&like', '&or', '&and');

    public static function isOperator($element)
    {
        if (!is_array($element) && !is_object($element)) {
            return FALSE;
        }

        foreach ($element as $attributeName => $value) {
            if ( !in_array($attributeName, self::$operators) ) {
                return FALSE;
            }
        }

        return TRUE;
    }

    public static function getValue($element)
    {
        $array = (array)$element;
        return current($array);
    }

    public static function getOperator($element)
    {
        $array = (array)$element;
        return key($array);
    }

    public static function getSQLOperator($operator)
    {
        switch ($operator) {
            case '&gt':
                return '>';

            case '&gte':
                return '>=';

            case '&lt':
                return '<';

            case '&lte':
                return '<=';

            case '&in':
                return 'IN';

            case '&nin':
                return 'NOT IN';

            case '&ne':
                return '!=';

            case '&eq':
                return '=';

            case '&like':
                return 'LIKE';
        }

        return NULL;
    }

}