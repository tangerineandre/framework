<?php
namespace Phidias\Component;

use Phidias\Component\Session;

class Persistent
{
    private $preventPersistence;

    private static $singleton;

    public static function singleton()
    {
        if (!isset(self::$singleton)) {
            $class           = get_called_class();
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
        if ($this->preventPersistence) {
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

    public function forget()
    {
         Session::clear('persistent_component:'.get_called_class());
         $this->preventPersistence = true;
    }
}