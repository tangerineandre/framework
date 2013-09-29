<?php
namespace Phidias\ORM;

use Phidias\ORM\DB;
use Phidias\ORM\DB\Table;

use Phidias\ORM\Exception\EntityNotFound;

/*
 *
 * An entity provides a definition for object persistence.
 * It specifies the object's attributes, and how they are mapped to table columns
 * or related to other entities
 *
 */
class Entity extends Dataset
{
    protected static $_schema;
    protected static $_validation;

    private $_keys;

    public static function getDB()
    {
        return DB::connect(isset(static::$_schema['db']) ? static::$_schema['db'] : NULL);
    }

    public static function getValidation()
    {
        return static::$_validation;
    }

    public static function single($id = NULL)
    {
        $class = get_called_class();
        return new $class($id);
    }

    public static function collection($identifier = NULL)
    {
        return new Collection($identifier === NULL ? get_called_class() : $identifier);
    }

    public static function pseudoCollection($schema)
    {
        return new PseudoCollection(get_called_class(), $schema);
    }

    public static function table()
    {
        $className = get_called_class();
        $object = new $className;
        return new Table($object->getSchema(), $className::getDB());
    }

    public function getAttributes()
    {
        $retval = array();
        foreach($this as $attribute => $value) {
            if (substr($attribute, 0, 1) != '_') {
                $retval[] = $attribute;
            }
        }

        return $retval;
    }

    public function toArray()
    {
        $retval = array();
        foreach ($this->getAttributes() as $attributeName) {
            $retval[$attributeName] = isset($this->$attributeName) ? $this->$attributeName : NULL;
        }
        return $retval;
    }

    public function toJSON()
    {
        return json_encode($this->toArray());
    }

    public function __construct($keys = NULL)
    {
        parent::__construct();
        $this->_returnType = Dataset::TYPE_SINGLE;

        if ( is_array($keys) ) {
            $this->_keys = $keys;
        } else if ($keys !== NULL) {
            $keyAttributes  = array_keys(static::$_schema['keys']);
            $keyAttribute   = $keyAttributes[0];

            $this->_keys = array(
                $keyAttribute => $keys
            );
        }

        /* Set object attributes */
        if ($this->_keys !== NULL) {
            foreach ($this->_keys as $attributeName => $value) {
                $this->$attributeName = $value;
            }
        }
    }

    public function setValues($values)
    {
        foreach ($this->getAttributes() as $attributeName) {
            if (isset($values[$attributeName])) {
                $this->$attributeName = $values[$attributeName];
            }
        }

        return $this;
    }

    public function find()
    {
        $schema     = $this->getSchema();
        $identifier = basename($this->_className);

        foreach ( array_keys($schema['keys']) as $keyAttribute ) {
            if ( isset($this->_keys[$keyAttribute]) ) {
                $this->equals("$identifier.$keyAttribute", $this->_keys[$keyAttribute]);
            }
        }
        $this->limit(1);

        return parent::find();
    }

    public function required()
    {
        $retval = $this->find();

        if ($retval === NULL) {
            throw new EntityNotFound($this);
        }

        return $retval;
    }



    public function getSchemaAttributes($includeKeys = FALSE)
    {
        $attributes = array();

        if ($includeKeys) {
            foreach ( static::$_schema['keys'] as $keyName => $keyData ) {
                $attributes[isset($keyData['column']) ? $keyData['column'] : $keyName] = $keyName;
            }
        }

        if ( isset(static::$_schema['attributes']) ) {
            foreach ( static::$_schema['attributes'] as $attributeName => $attributeData ) {
                $attributes[isset($attributeData['column']) ? $attributeData['column'] : $attributeName] = $attributeName;
            }
        }

        if ( isset(static::$_schema['relations']) ) {
            foreach ( static::$_schema['relations'] as $attributeName => $relatedData ) {
                $attributes[isset($relatedData['column']) ? $relatedData['column'] : $attributeName] = $attributeName;
            }
        }

        return $attributes;
    }


    private function prepareColumnValue($value, $targetAttribute = NULL)
    {
        if (is_a($value, 'Phidias\ORM\Entity')) {
            $value = $value->toArray();
        }

        if (is_array($value) && isset(static::$_schema['relations'][$targetAttribute]['entity'])) {
            $relatedEntityClass = static::$_schema['relations'][$targetAttribute]['entity'];
            $relatedSchema      = $relatedEntityClass::$_schema;
            $foreignKeys        = array_keys($relatedSchema['keys']);

            if ( isset($value[$foreignKeys[0]]) ) {
                $value = $value[$foreignKeys[0]];
            } else {
                return 'NULL';
            }
        }

        return "'".DB::escapeString($value)."'";
    }


    public function save()
    {
        $attributes = $this->getSchemaAttributes(TRUE);

        $query = "INSERT INTO ".static::$_schema['table'] ."\n";
        $query .= "(".implode(', ',array_keys($attributes)).")" ."\n";
        $query .= "VALUES". "\n";

        $values = array();
        foreach ($attributes as $attributeName) {
            $values[] = isset($this->$attributeName) ? $this->prepareColumnValue($this->$attributeName, $attributeName) : 'NULL';
        }
        $query .= "(".implode(',', $values).")". "\n";

        $db = self::getDB();

        $db->query($query);

        foreach ( static::$_schema['keys'] as $keyName => $keyData ) {
            if ( isset($keyData['autoIncrement']) && $keyData['autoIncrement'] ) {
                $this->$keyName = $db->getInsertID();
            }
        }
    }


    public function update()
    {
        if ( !isset(static::$_schema['attributes']) ) {
            return 0;
        }

        $query = "UPDATE ".static::$_schema['table'] ."\n";

        $values = array();
        foreach ($this->getSchemaAttributes() as $column => $attributeName) {
            $values[] = $column." = ".(isset($this->$attributeName) ? $this->prepareColumnValue($this->$attributeName, $attributeName) : 'NULL');
        }

        if ( !count($values) ) {
            return 0;
        }

        $query .= "SET ". implode(', ', $values). "\n";

        $query .= "WHERE ";

        $conditions = array();
        foreach ( static::$_schema['keys'] as $attributeName => $keyData ) {
            $column = isset($keyData['column']) ? $keyData['column'] : $attributeName;

            if ( !isset($this->$attributeName) ) {
                return 0;
            }

            $conditions[] = $column." = ".$this->prepareColumnValue($this->$attributeName, $attributeName);
        }
        $query .= implode(" AND ", $conditions) ."\n";

        $query .= "LIMIT 1";

        $db = self::getDB();
        $db->query($query);

        return $db->affectedRows();
    }

    public function delete()
    {
        $keyConditions = array();
        foreach ( static::$_schema['keys'] as $keyName => $keyData ) {
            if (!isset($this->$keyName) || $this->$keyName === NULL) {
                return;
            }

            $column = isset($keyData['column']) ? $keyData['column'] : $keyName;
            $keyConditions[] = $column." = ".$this->prepareColumnValue($this->$keyName, $keyName);
        }

        $query = "DELETE FROM ".static::$_schema['table'] ."\n";
        $query .= "WHERE ".implode(' AND ', $keyConditions) ."\n";
        $query .= "LIMIT 1";

        return self::getDB()->query($query);
    }

}