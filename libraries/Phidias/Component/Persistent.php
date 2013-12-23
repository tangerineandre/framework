<?php
namespace Phidias\Component;

use Phidias\Component\Session;

class Persistent
{
    private $clearing;

    private static $singleton;

    public static function singleton()
    {
        $class = get_called_class();
        if (!isset(self::$singleton)) {
            self::$singleton = new $class;
        }

        return self::$singleton;
    }


    public function __construct()
    {
        if ($values = Session::get('persistent_component:'.get_called_class())) {
            foreach ($values as $attributeName => $value) {
                $this->$attributeName = $value;
            }
        }
    }

    public function __destruct()
    {
        if ($this->clearing) {
            return;
        }

        $class      = get_called_class();
        $attributes = array_keys(get_class_vars($class));
        $values     = array();
        foreach ($attributes as $attributeName) {
            $values[$attributeName] = isset($this->$attributeName) ? $this->$attributeName : NULL;
        }

        Session::set('persistent_component:'.$class, $values);
    }

    public function clear()
    {
         Session::clear('persistent_component:'.get_called_class());
         $this->clearing = true;
    }
}