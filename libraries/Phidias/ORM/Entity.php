<?php
namespace Phidias\ORM;

use Phidias\DB\Iterator;

class Entity
{
    protected static $map;

    /* Primary key values as stored in the database.  If this value is NULL, the object is assumed to not be stored yet */
    private $_id;

    private $workingAttributes;

    public function __construct($_id = NULL, $autoFetch = TRUE)
    {
        if ($_id !== NULL) {

            $this->setID($_id);

            if ($autoFetch) {
                $probe = self::single()->allAttributes()->find($_id);
                $this->setValues((array)$probe);
            }
        }
    }

    public function useAttributes($attributeNames = NULL)
    {
        if ($this->workingAttributes === NULL) {
            $this->workingAttributes = array();
        }

        $mapAttributes = $this->getMap()->getAttributes();

        if (!is_array($attributeNames)) {
            $attributeNames = func_get_args();
        }

        foreach ($attributeNames as $attributeName) {
            if (isset($mapAttributes[$attributeName])) {
                $this->workingAttributes[$attributeName] = $attributeName;
            }
        }

        return $this;
    }

    public function getID()
    {
        return $this->_id;
    }

    public function setID($_id)
    {
        $this->_id = $_id;

        $idValue    = (array)$_id;
        $map        = self::getMap();
        foreach ($map->getKeys() as $index => $attributeName) {
            $this->$attributeName = isset($idValue[$index]) ? $idValue[$index] : NULL;
        }

    }

    public function clearID()
    {
        $this->_id = NULL;
    }

    public function setValues($values, $acceptedAttributes = NULL)
    {
        if (!is_array($values) && !is_object($values)) {
            return;
        }

        $map = self::getMap();

        foreach ($values as $attribute => $value) {

            if (!$map->hasAttribute($attribute)) {
                continue;
            }

            if ($acceptedAttributes !== NULL && !in_array($attribute, $acceptedAttributes, true)) {
                continue;
            }

            if ($map->hasRelation($attribute) && (is_array($value)||is_object($value))) {
                if (!($this->$attribute instanceof Entity)) {
                    $relationData       = $map->getRelation($attribute);
                    $this->$attribute   = new $relationData['entity'];
                }
                $this->$attribute->setValues($value);
            } else {
                $this->$attribute = $value;
            }

        }
    }

    public function getPrimaryKeyValue()
    {
        $retval = array();
        $map    = self::getMap();
        foreach ($map->getKeys() as $attributeName) {
            if (isset($this->$attributeName)) {
                $retval[$attributeName] = $this->$attributeName;
            } else {
                return NULL;
            }
        }

        return $retval;
    }


    public function fetchAll()
    {
        $retval = clone($this);

        foreach (get_object_vars($retval) as $attributeName => $value ) {
            if ($value instanceof \Phidias\DB\Iterator) {
                $retval->$attributeName = $retval->$attributeName->fetchAll();
            }
        }

        return $retval;
    }

    public function toArray()
    {
        return (array)$this->fetchAll();
    }

    public function toJSON()
    {
        return json_encode($this->fetchAll());
    }


    /* Factories */
    public static function getMap()
    {
        $className = get_called_class();
        return new Entity\Map($className::$map);
    }

    public static function iterator($key, $singleElement = FALSE)
    {
        return new Iterator(get_called_class(), $key, $singleElement);
    }

    public static function collection($alias = NULL)
    {
        $className = get_called_class();
        $retval    = new Collection(new $className);

        if ($alias !== NULL) {
            $retval->setAlias($alias);
        }

        if (is_callable(array($className, 'preFilter'))) {
            $retval->addPreFilter(array($className, 'preFilter'));
        }

        if (is_callable(array($className, 'postFilter'))) {
            $retval->addPostFilter(array($className, 'postFilter'));
        }

        return $retval;
    }

    public static function single($alias = NULL)
    {
        $className = get_called_class();
        $retval    = new Collection(new $className, TRUE);

        if ($alias !== NULL) {
            $retval->setAlias($alias);
        }

        if (is_callable(array($className, 'preFilter'))) {
            $retval->addPreFilter(array($className, 'preFilter'));
        }

        if (is_callable(array($className, 'postFilter'))) {
            $retval->addPostFilter(array($className, 'postFilter'));
        }

        return $retval;
    }

    public static function table()
    {
        return new Table(self::getMap());
    }


    /* Tools and helpers */


    /*
     * Given an array containing either values, objects or arrays,
     * assume the objects and arrays are instances of this entity, and extract their
     * current ID.
     *
     */
    public static function extractKeys($array)
    {
        $primaryKeys    = self::getMap()->getKeys();
        $key            = $primaryKeys[0];

        if (is_object($array) && isset($array->$key)) {
            return $array->$key;
        }

        if (isset($array[$key])) {
            return $array[$key];
        }

        $retval = array();
        foreach ((array)$array as $element) {
            if (is_array($element)) {

                if (isset($element[$key])) {
                    $retval[] = $element[$key];
                }

            } else if (is_object($element) ) {

                if (isset($element->$key)) {
                    $retval[] = $element->$key;
                }

            } else {
                $retval[] = $element;
            }
        }

        return $retval;
    }

    public static function getNextAutoIncrementValue()
    {
        $map    = self::getMap();
        $db     = \Phidias\DB::connect($map->getDB());
        $table  = $map->getTable();

        $result = $db->query("SHOW TABLE STATUS LIKE '$table'");
        $row    = $result->fetch_assoc();

        return isset($row['Auto_increment']) ? $row['Auto_increment'] : NULL;
    }


    /* Shorthand methods */
    private function getCollection()
    {
        $collection = self::single();

        if ($this->workingAttributes !== NULL) {
            foreach ($this->workingAttributes as $attributeName) {
                $collection->attr($attributeName);
            }
        }

        $map = self::getMap();
        foreach (array_keys($map->getRelations()) as $attributeName) {
            if (isset($this->$attributeName) && $this->$attributeName instanceof Entity) {
                $collection->attr($attributeName, $this->$attributeName->getCollection());
            }
        }

        return $collection;
    }

    public function save()
    {
        return $this->getCollection()->save($this);
    }

    public function delete()
    {
        return self::single()->delete($this);
    }

}