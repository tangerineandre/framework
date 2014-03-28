<?php
/*

ORM Entity

En ORM entity is declared by extending this class and declaring a MAP
in the static variable $map:

e.g.:

myModule/libraries/Book/Entity.php:

<?php

namespace Book;

class Entity extends \Phidias\ORM\Entity
{
    var $id;
    var $title;
    .... declare all attributes


    //MAP definition:

    protected static $map = array(

        'db'    => 'DB_NAME'            //the name of the database (as configured via phidias.db.DB_NAME.property).
        'table' => '.....',             //the name of the table
        'keys'  => array('id' [, ...]), //array identifying one or more columns as keys

        'attributes' => array(

            'id' => array(
                'type'          => 'integer',
                'unsigned'      => TRUE,
                'autoIncrement' => TRUE
            ),

            'title' => array(
                'type'          => 'varchar',
                'length'        => 128,
                'acceptNull'    => TRUE,
                'default'       => NULL
            ),

            

            .... possible declarations:


            'attributeName' => array(
                'column'        => '',                  //corresponding column in the database.  Defaults to attribute name
                'length'        => 1,                   //column type length. Defaults to DB Engine default
                'autoIncrement' => FALSE,               //use an AUTO_INCREMENT key.  Defaults to FALSE
                'unsigned'      => TRUE,                //use UNSIGNED for numeric field.  Defaults to DB Engine default (FALSE)
                'acceptNull'    => TRUE,                //Defines if the column can be null.  Defaults to DB Engine default (FALSE)
                'default'       => NULL                 //Default value.  Defaults to DB Engine default (NONE)
            ),


            //Declare a foreign key to another entity 

            'relationName' => array(
                'column'        => '',                  //corresponding column in the database.  Default to attribute name
                'entity'        => 'Author\Entity',     //full class name of the related entity
                'attribute'     => 'id',                //related attribute (usually the related entity's primary key)
                'acceptNull'    => TRUE,
                'default'       => NULL                
            )

        ),


        //Declare column indexes (ADD INDEX):

        'indexes' => array(
            'lastname1' => 'lastname1',
            'lastname2' => 'lastname2',
            'username'  => 'username'
        ),


        //Declare unique indexes (ADD UNIQUE):

        'unique' => array(
            array('person', 'token')
            .... [attribute name, or array of attribute names] ...
        )
    );


}




*/


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

    public static function collection()
    {
        $className = get_called_class();
        $retval    = new Collection(new $className);

        if (is_callable(array($className, 'preFilter'))) {
            $retval->addPreFilter(array($className, 'preFilter'));
        }

        if (is_callable(array($className, 'postFilter'))) {
            $retval->addPostFilter(array($className, 'postFilter'));
        }

        return $retval;
    }

    public static function single($primaryKeyValue = NULL)
    {
        $className = get_called_class();
        $retval    = new Collection(new $className, TRUE);

        if ($primaryKeyValue !== NULL) {
            $retval->whereKey($primaryKeyValue);
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
        } else {
            $collection->allAttributes();
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