<?php
namespace Phidias\ORM;

class Operator
{
    private static $operators = array('&gt', '&gte', '&lt', '&lte', '&in', '&nin', '&ne', '&eq', '&like', '&or', '&and');

    public static function isOperator($array)
    {
        if (!is_array($array)) {
            return FALSE;
        }

        foreach ($array as $attributeName => $value) {
            if ( !in_array($attributeName, self::$operators) ) {
                return FALSE;
            }
        }

        return TRUE;
    }

    public static function getValue($operation)
    {
        return current($operation);
    }

    public static function getOperator($operation)
    {
        return key($operation);
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