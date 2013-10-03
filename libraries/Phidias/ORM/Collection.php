<?php
namespace Phidias\ORM;

use Phidias\DB;
use Phidias\DB\Select;
use Phidias\ORM\Collection\Iterator;

class Collection
{
    private $entity;        //Entity definition
    private $map;

    private $attributes;    //Selected attributes
    private $nestedCollections;
    private $nestingRelation;

    /*
     * Suppose the following scenario:
     *
     * $person = Person::collection()
     *              ->attr('name')
     *              ->attr('birthDay', 'FROM_UNIXTIME(Person.birthDay)')
     *              ->where('Person.birthDay ...some condition')
     *
     * since "birthDay" is not a column in the query, in order to be referenced in the WHERE as "Person.birthDay"
     * we must keep track of attributes obtained via a SQL construct.  These are refered to as "custom attributes"
     */
    private $customAttributes;

    private $alias;
    private $db;
    private $resultSet;

    private $selectWhere;
    private $selectOrder;
    private $selectLimit;

    private static $nextAlias = 0;

    private $hasOneElement;

    private $unitOfWork;

    /* Attribute values to be updated */
    private $updateValues;

    public function __construct($entity, $map, $hasOneElement = FALSE)
    {
        $this->entity               = $entity;
        $this->map                  = $map;
        $this->attributes           = array();
        $this->nestedCollections    = array();
        $this->nestingRelation      = NULL;

        $this->customAttributes     = array();

        /* Allocate a unique alias */
        $this->alias = $this->map['table'].self::$nextAlias++;

        /* Connect to the DB */
        $this->db = DB::connect($this->map['db']);

        /* Select options */
        $this->selectWhere          = array();
        $this->selectOrder          = array();
        $this->selectLimit          = NULL;


        /* Set initial attributes: Key attributes are ALWAYS selected */
        foreach ($this->map['keys'] as $keyAttributeName) {
            $this->attr($keyAttributeName);
        }

        /* Special cases: collections with one element */
        $this->hasOneElement    = $hasOneElement;
    }

    public function getDB()
    {
        return $this->db;
    }

    public function getMap()
    {
        return $this->map;
    }

    public function getAttributes()
    {
        return $this->attributes;
    }

    public function getNestedCollections()
    {
        return $this->nestedCollections;
    }


    public function relatedWith($relationName)
    {
        $this->nestingRelation = $relationName;

        return $this;
    }


    public function attr($attributeName, $attributeSource = NULL, $parameters = NULL)
    {
        if ($attributeSource instanceof Collection) {

            $nestedCollection = $attributeSource;

            /* Determine the relation for nesting */
            $nestingRelationName    = isset($nestedCollection->nestingRelation) ? $nestedCollection->nestingRelation : $attributeName;
            $nestedMap              = $nestedCollection->map;
            $localMap               = $this->map;

            if ( isset($localMap['relations'][$nestingRelationName]) ) {

                $localColumn    = $localMap['relations'][$nestingRelationName]['column'];
                $foreignColumn  = $localMap['relations'][$nestingRelationName]['attribute'];

            } elseif ( isset($nestedMap['relations'][$nestingRelationName]) ) {

                $localColumn    = $nestedMap['relations'][$nestingRelationName]['attribute'];
                $foreignColumn  = $nestedMap['relations'][$nestingRelationName]['column'];

            } else {
                trigger_error("Relation '$nestingRelationName' not found.  Specify the related attribute with collection::relatedWith", E_USER_ERROR);
            }

            $this->nestedCollections[$attributeName] = array(
                'foreignCollection' => $nestedCollection,
                'foreignColumn'     => $foreignColumn,
                'localColumn'       => $localColumn,
            );

            return $this;
        }

        if (gettype($attributeSource) == 'string') {
            if (is_array($parameters)) {
                $attributeSource = $this->db->bindParameters($attributeSource, $parameters);
            }
            $this->customAttributes[$attributeName] = $attributeSource;
        }

        $this->attributes[$attributeName] = $attributeSource;

        return $this;
    }

    public function allAttributes()
    {
        foreach ($this->map['attributes'] as $attributeName => $attributeData) {
            $this->attr($attributeName);
        }

        return $this;
    }


    public function where($stringCondition, $parameters = NULL)
    {
        /* Bind parameters */
        if ( is_array($parameters) ) {
            $stringCondition = $this->db->bindParameters($stringCondition, $parameters);
        }
        $this->selectWhere[] = $stringCondition;

        return $this;
    }

    public function whereKey($keyValue)
    {
        if (!is_array($keyValue)) {
            $keyValue = (array)$keyValue;
        }

        foreach ($this->map['keys'] as $index => $attributeName) {
            if (!isset($keyValue[$index])) {
                continue;
            }
            $this->where("$attributeName = :v", array('v' => $keyValue[$index]));
        }
    }

    public function equals($attributeName, $value)
    {
        if ( $value === NULL ) {
            $this->selectWhere[] = $attributeName.' IS NULL';
        } else {
            $sanitizedValue = $this->db->sanitizeValue($value);

            if ($sanitizedValue !== NULL) {
                $this->selectWhere[] = $attributeName." = ".$sanitizedValue;
            }
        }

        return $this;
    }

    public function limit($value)
    {
        $this->selectLimit = $value;
        return $this;
    }

    public function orderBy($value)
    {
        $this->selectOrder[] = $value;
        return $this;
    }


    public function find($primaryKeyValue = NULL)
    {
        $iterator = new Iterator($this);

        if ($this->hasOneElement) {
            $this->limit(1);
            if ($primaryKeyValue !== NULL) {
                $this->whereKey($primaryKeyValue);
            }

            $iterator = $iterator->first();
            if ($iterator === NULL) {
                throw new Exception\EntityNotFound(get_class($this->entity), implode(', ', (array)$primaryKeyValue));
            }

        }

        return $iterator;
    }

    public function count()
    {
        $select = $this->buildSelect();

        $select->limit(NULL);
        $select->orderBy(NULL);
        $select->field(NULL);

        $select->field('count', 'COUNT(*)');

        $resultSet  = $this->db->select($select);
        $retval     = $resultSet->fetch_assoc();

        return isset($retval['count']) ? $retval['count'] : NULL;
    }


    /* Data for creating the query and obtaining the resultSet */
    public function getAlias()
    {
        return $this->alias;
    }

    public function getEntity()
    {
        return $this->entity;
    }

    public function toObject($row, $pointer, $resultSet)
    {
        /* First, determine the object's ID */
        $id = array();
        foreach ($this->map['keys'] as $keyName) {
            $keyFieldName = $this->alias.'_'.$keyName;
            $id[] = $row[$keyFieldName]; //we can assume this value is set since the collection  ALWAYS includes the key attributes
        }

        /* Create the new Entity */
        $returnClassName    = get_class($this->entity);
        $returnObject       = new $returnClassName($id, FALSE);

        /* Set all the attributes */
        foreach (array_keys($this->attributes) as $attributeName) {
            $returnObject->$attributeName = $row[$this->alias.'_'.$attributeName];
        }

        /* Get all nested collections */
        if ($this->nestedCollections) {

            /* Build conditions to determine which record in the resultset correspond to this entities nested objects (i.e. its "restrictions") */
            $restrictions = array();
            foreach ($this->map['keys'] as $keyName) {
                $keyFieldName = $this->alias.'_'.$keyName;

                $restrictions[] = array(
                    'column'    => $keyFieldName,
                    'value'     => $row[$keyFieldName]
                );
            }

            foreach ($this->nestedCollections as $attributeName => $nestedCollectionData) {
                $iterator = new Iterator($nestedCollectionData['foreignCollection'], $resultSet, $pointer, $restrictions);
                $returnObject->$attributeName = $nestedCollectionData['foreignCollection']->hasOneElement ? $iterator->first() : $iterator;
            }
        }

        return $returnObject;
    }

    public function getResultSet()
    {
        if ( $this->resultSet === NULL ) {
            $this->resultSet = $this->db->select($this->buildSelect());
        }

        return $this->resultSet;
    }

    public function buildSelect($aliasMap = NULL)
    {
        if ($aliasMap === NULL) {
            $aliasMap = $this->buildAliasMap();
        }

        $select = new Select($this->map['table'], $this->alias);


        /* Defined options */
        foreach ($this->selectWhere as $condition) {
            $select->where($this->translate($condition, $aliasMap));
        }

        foreach ($this->selectOrder as $order) {
            $select->orderBy($this->translate($order, $aliasMap));
        }

        $select->limit($this->selectLimit);

        /* Select collection attributes */
        foreach ($this->attributes as $attributeName => $attributeSource) {

            if ($attributeSource === NULL) {
                $columnName         = $this->map['attributes'][$attributeName]['column'];
                $attributeSource    = $this->alias.'.'.$columnName;
            } else {
                $attributeSource    = $this->translate($attributeSource, $aliasMap);
            }

            $select->field($this->alias.'_'.$attributeName, $attributeSource);
        }

        /* Join with nested collections */
        foreach($this->nestedCollections as $attributeName => $nestedCollectionData) {

            $nestedCollection   = $nestedCollectionData['foreignCollection'];
            $nestedMap          = $nestedCollection->map;
            $nestedSelect       = $nestedCollection->buildSelect($aliasMap);

            $joinConditions = array();
            foreach ($nestedCollection->selectWhere as $condition) {
                $joinConditions[] = $this->translate($condition, $aliasMap);
            }
            $joinCondition = $joinConditions ? implode(' AND ', $joinConditions) : NULL;

            $select->leftJoin($nestedMap['table'], $nestedCollection->alias, $nestedCollectionData['foreignColumn'], $nestedCollectionData['localColumn'], $joinCondition);

            $select->merge($nestedSelect);

            foreach ($nestedCollection->selectOrder as $order) {
                $select->orderBy($this->translate($order, $aliasMap));
            }
        }


        return $select;
    }


    /*
     * Every collection has an identifier.
     * The BASE collection will use the entity name, while
     * nested collections will use the parent's identifier followed by .[attributeName].
     *
     * Consider this example:

        $person = Person::collection()
                ->attr('name')
                ->attr('contactData', Person_Data::collection()
                    ->attr('address')
                    ->attr('fullMobile', 'CONCAT(Person.name, Person.contactData.mobile)')
                )
                ->where('Person.name = :name', array('name' => 'Santiago'))
                ->where('Person.contactData.mobile LIKE :mobile', array('module' => '313%'))
     *
     * will result in the following query:
     *
     * SELECT
     *  a0.name as a0_name,
     *  a1.address as a1_address, CONCAT(a0.name, a1.mobile) as a1_fullMobile
     * FROM people a0
     * LEFT JOIN people_data a1 ON a1.person = a0.id
     * WHERE
     * a0.name = 'Santiago' AND a1.mobile LIKE '313%'
     *
     * The alias map will contain a dictionary that translates every possible attribute in the collection
     * to its corresponding aliased column in the query:
     *
     * Person.id    => a0.id
     * Person.name  => a0.name
     * Person.firstName => a0.first_name
     * ....
     * Person.contactData.address   => a1.address
     * Person.contactData.phone     => a1.phone
     *
     */
    private function buildAliasMap($identifier = NULL, &$retval = NULL)
    {
        if ($identifier === NULL) {
            $identifier = basename(get_class($this->entity));
        }

        foreach ($this->nestedCollections as $attributeName => $nestedCollection) {
            $nestedCollection['foreignCollection']->buildAliasMap("$identifier.$attributeName", $retval);
        }

        foreach ($this->map['attributes'] as $attributeName => $attributeData) {
            $retval["$identifier.$attributeName"] = $this->alias.'.`'.$attributeData['column'].'`';
        }

        foreach ($this->customAttributes as $attributeName => $attributeSource) {
            $retval["$identifier.$attributeName"] = $this->translate($attributeSource, $retval);
        }

        return $retval;
    }

    /*
     * Translate a string using the given alias map
     */
    private function translate($string, array $aliasMap)
    {
        return $string === NULL ? NULL : str_replace(array_keys($aliasMap), $aliasMap, $string);
    }




    /* Unit of work functions */
    public function add($entity)
    {
        if ($this->unitOfWork === NULL) {
            $this->unitOfWork = new Collection\UnitOfWork($this);
        }

        $this->unitOfWork->add($entity);

        return $this;
    }

    public function save($entity = NULL)
    {
        if ($entity === NULL) {
            return $this->unitOfWork === NULL ? NULL : $this->unitOfWork->save();
        }

        /* Saving a single entity:
         * Use this collection definition to determine
         * which attributes and nested entities should be
         * inserted or updated
         */
        $values = array();
        foreach (array_keys($this->attributes) as $attributeName) {
            $columnName = $this->map['attributes'][$attributeName]['column'];
            if (isset($this->nestedCollections[$attributeName])) {

                try {
                    $values[$columnName] = isset($entity->$attributeName) ? $this->nestedCollections[$attributeName]['foreignCollection']->save($entity->$attributeName) : NULL;
                } catch (\Phidias\DB\Exception\DuplicateKey $e) {
                    $values[$columnName] = $e->getKey() == 'PRIMARY' ? $e->getEntry() : NULL;
                }

            } else {
                $values[$columnName] = isset($entity->$attributeName) ? $entity->$attributeName : NULL;
            }
        }

        $entityID = $entity->getID();
        if ($entityID) {

            $entityID       = (array)$entityID;
            $idConditions   = array();
            $idValues       = array();

            foreach ($this->map['keys'] as $index => $attributeName) {
                $columnName                 = $this->map['attributes'][$attributeName]['column'];
                $idConditions[]             = "$columnName = :$attributeName";
                $idValues[$attributeName]   = isset($entityID[$index]) ? $entityID[$index] : NULL;
            }
            $this->db->update($this->map['table'], $values, implode(' AND ', $idConditions), $idValues);

        } else {
            $this->db->insert($this->map['table'], $values);
        }

        $newID = array();
        foreach ($this->map['keys'] as $attributeName) {
            $newID[] = isset($this->map['attributes'][$attributeName]['autoIncrement']) ? $this->db->getInsertID() : $entity->$attributeName;
        }
        $entity->setID($newID);

        return array_pop($newID);
    }

    public function clear()
    {
        return $this->unitOfWork === NULL ? NULL : $this->unitOfWork->clear();
    }

    /* Functions for updating */
    public function set($attributeName, $value = NULL)
    {
        if (is_array($attributeName) && $value === NULL) {
            foreach ($attributeName as $name => $value) {
                $this->set($name, $value);
            }
            return $this;
        }

        if (!isset($this->map['attributes'][$attributeName])) {
            return $this;
        }

        $this->updateValues[$this->map['attributes'][$attributeName]['column']] = $value;

        return $this;
    }

    public function update($force = FALSE)
    {
        if (!$this->selectWhere && !$force) {
            trigger_error("attempt to update ignored because no conditions are defined.  If you wish to update the entire collection invoke update(TRUE)");
            return 0;
        }

        if ($this->selectWhere) {
            $aliasMap           = $this->buildAliasMap();
            $updateConditions   = array();
            foreach($this->selectWhere as $where) {
                $updateConditions[] = $this->translate($where, $aliasMap);
            }
            $updateCondition = implode(' AND ', $updateConditions);
        } else {
            $updateCondition = NULL;
        }

        return $this->db->update($this->map['table'].' '.$this->alias, $this->updateValues, $updateCondition);
    }

    public function delete($entity = NULL)
    {
        if ($entity !== NULL) {
            $this->whereKey($entity->getID());
        }

        if (!$this->selectWhere) {
            trigger_error("attempt to delete ignored because no conditions are defined.  If you wish to delete the entire collection use the conditional where(1)");
            return 0;
        }

        $aliasMap           = $this->buildAliasMap();
        $deleteConditions   = array();
        foreach($this->selectWhere as $where) {
            $deleteConditions[] = $this->translate($where, $aliasMap);
        }

        /* Since MySQL does not support "DELETE FROM table a WHERE a.some = thing" */
        $deleteCondition = str_replace($this->alias.'.', '', implode(' AND ', $deleteConditions));

        return $this->db->delete($this->map['table'], $deleteCondition);
    }

    public function getInsertID()
    {
        return $this->db->getInsertID();
    }

}