<?php
namespace Phidias\ORM;

use Phidias\DB;
use Phidias\DB\Select;
use Phidias\ORM\Collection\Iterator;

class Collection
{
    private $entity;        //Entity definition
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

    private $hasSingleElement;

    private $unitOfWork;

    /* Attribute values to be updated */
    private $updateValues;


    public function __construct($entity, $hasSingleElement = FALSE)
    {
        $this->entity               = $entity;
        $this->attributes           = array();
        $this->nestedCollections    = array();
        $this->nestingRelation      = NULL;

        $this->customAttributes     = array();

        /* Allocate a unique alias */
        $this->alias = 'a'.self::$nextAlias++;

        /* Connect to the DB */
        $map = $this->entity->getMap();
        $this->db = DB::connect(isset($map['db']) ? $map['db'] : NULL);

        /* Select options */
        $this->selectWhere          = array();
        $this->selectOrder          = array();
        $this->selectLimit          = NULL;

        $this->hasSingleElement     = $hasSingleElement;
    }

    public function getDB()
    {
        return $this->db;
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
            $nestedMap              = $nestedCollection->entity->getMap();
            $localMap               = $this->entity->getMap();

            if (isset($nestedMap['relations'][$nestingRelationName])) {

                $localColumn    = $nestedMap['relations'][$nestingRelationName]['attribute'];
                $foreignColumn  = $nestingRelationName;

            } elseif (isset($localMap['relations'][$nestingRelationName])) {

                $localColumn    = $nestingRelationName;
                $foreignColumn  = $localMap['relations'][$nestingRelationName]['attribute'];

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
        $map = $this->entity->getMap();
        foreach ($map['attributes'] as $attributeName => $attributeData) {
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


    public function find()
    {
        $iterator = new Iterator($this);
        return $this->hasSingleElement ? $iterator->first() : $iterator;
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
        $map                = $this->entity->getMap();
        $returnClassName    = get_class($this->entity);
        $returnObject       = new $returnClassName;

        foreach (array_keys($this->attributes) as $attributeName) {
            $returnObject->$attributeName = $row[$this->alias.'_'.$attributeName];
        }

        /* Build restrictions */
        $restrictions = array();
        foreach ($map['keys'] as $keyName) {
            $keyFieldName = $this->alias.'_'.$keyName;
            $restrictions[] = array(
                'column'    => $keyFieldName,
                'value'     => $row[$keyFieldName]
            );

            $returnObject->$keyName = $row[$keyFieldName];
        }

        foreach ( $this->nestedCollections as $attributeName => $nestedCollectionData ) {
            $iterator = new Iterator($nestedCollectionData['foreignCollection'], $resultSet, $pointer, $restrictions);
            $returnObject->$attributeName = $nestedCollectionData['foreignCollection']->hasSingleElement ? $iterator->first() : $iterator;
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

        $map    = $this->entity->getMap();

        $select = new Select($map['table'], $this->alias);


        /* Defined options */
        foreach ($this->selectWhere as $condition) {
            $select->where($this->translate($condition, $aliasMap));
        }

        foreach ($this->selectOrder as $order) {
            $select->orderBy($this->translate($order, $aliasMap));
        }

        $select->limit($this->selectLimit);


        /* Always select primary keys */
        foreach($map['keys'] as $keyName) {
            $columnName = isset($map['attributes'][$keyName]['name']) ? $map['attributes'][$keyName]['name'] : $keyName;
            $select->field($this->alias.'_'.$columnName, "$this->alias.$columnName");
        }

        /* Select collection attributes */
        foreach ($this->attributes as $attributeName => $attributeSource) {

            if ($attributeSource === NULL) {
                $columnName = isset($map['attributes'][$attributeName]['name']) ? $map['attributes'][$attributeName]['name'] : $attributeName;
                $attributeSource = $this->alias.'.'.$columnName;
            } else {
                $attributeSource = $this->translate($attributeSource, $aliasMap);
            }

            $select->field($this->alias.'_'.$attributeName, $attributeSource);
        }

        /* Join with nested collections */
        foreach($this->nestedCollections as $attributeName => $nestedCollectionData) {

            $nestedCollection   = $nestedCollectionData['foreignCollection'];
            $nestedMap          = $nestedCollection->entity->getMap();
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
        $map = $this->entity->getMap();

        if ($identifier === NULL) {
            $identifier = basename(get_class($this->entity));
        }

        foreach ($this->nestedCollections as $attributeName => $nestedCollection) {
            $nestedCollection['foreignCollection']->buildAliasMap("$identifier.$attributeName", $retval);
        }

        foreach ($map['attributes'] as $attributeName => $attributeData) {
            $retval["$identifier.$attributeName"] = $this->alias.'.`'.((isset($attributeData['name']) ? $attributeData['name'] : $attributeName)).'`';
        }

        foreach ($this->customAttributes as $attributeName => $attributeSource) {
            $retval["$identifier.$attributeName"] = $this->translate($attributeSource, $retval);
        }

        if (isset($map['relations'])) {
            foreach(array_keys($map['relations']) as $attributeName) {
                $retval["$identifier.$attributeName"] = $this->alias.'.`'.$attributeName.'`';
            }
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
            $this->unitOfWork = new Collection\UnitOfWork($this->db, $this->entity->getMap());
        }

        $this->unitOfWork->add($entity);
    }

    public function save()
    {
        return $this->unitOfWork === NULL ? NULL : $this->unitOfWork->save();
    }

    public function clear()
    {
        return $this->unitOfWork === NULL ? NULL : $this->unitOfWork->clear();
    }

    /* Functions for updating */
    public function set($attributeName, $value)
    {
        $map = $this->entity->getMap();
        if (!isset($map['attributes'][$attributeName]) && !isset($map['relations'][$attributeName])) {
            trigger_error("attribute '$attributeName' not found");
            return $this;
        }

        $columnName = isset($map['attributes'][$attributeName]['name']) ? $map['attributes'][$attributeName]['name'] : $attributeName;
        $this->updateValues[$columnName] = $value;

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

        $map = $this->entity->getMap();
        return $this->db->update($map['table'].' '.$this->alias, $this->updateValues, $updateCondition);
    }

    public function delete($force = FALSE)
    {
        if (!$this->selectWhere && !$force) {
            trigger_error("attempt to delete ignored because no conditions are defined.  If you wish to delete the entire collection invoke delete(TRUE)");
            return 0;
        }

        if ($this->selectWhere) {
            $aliasMap           = $this->buildAliasMap();
            $deleteConditions   = array();
            foreach($this->selectWhere as $where) {
                $deleteConditions[] = $this->translate($where, $aliasMap);
            }
            $deleteCondition = implode(' AND ', $deleteConditions);

            /* Since MySQL does not support "DELETE FROM table a WHERE a.some = thing" */
            $deleteCondition = str_replace($this->alias.'.', '', $deleteCondition);

        } else {
            $deleteCondition = NULL;
        }

        $map = $this->entity->getMap();
        return $this->db->delete($map['table'], $deleteCondition);
    }


    public function remove($entity)
    {
        $map = $this->entity->getMap();

        $allKeysAreSet = TRUE;
        $deleteConditions = array();
        foreach ($map['keys'] as $keyName) {
            if (!isset($entity->$keyName)) {
                $allKeysAreSet = FALSE;
                break;
            }

            $columnName = isset($map['attributes'][$keyName]['name']) ? $map['attributes'][$keyName]['name'] : $keyName;
            $deleteConditions[] = "`".$columnName."` = ".$this->db->sanitizeValue($entity->$keyName);
        }

        if (!$allKeysAreSet) {
            return FALSE;
        }
        
        return $this->db->delete($map['table'], implode(' AND ', $deleteConditions));
    }


}