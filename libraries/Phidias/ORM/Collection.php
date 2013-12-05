<?php
namespace Phidias\ORM;

class Collection
{
    private $alias;

    private $entity;
    private $hasOneElement;

    private $attributes;
    private $joins;

    private $where;
    private $orderBy;
    private $groupBy;
    private $limit;

    private $postFilters;

    private $map;
    private $db;

    private $iterator;

    private $joinAsInner;
    private $relationAlias;

    /* DB Write functionality */
    private $unitOfWork;
    private $updateValues;

    public function __construct($entity, $hasOneElement = FALSE)
    {
        $this->alias            = get_class($entity);

        $this->entity           = $entity;
        $this->hasOneElement    = $hasOneElement;

        $this->attributes       = array();
        $this->joins            = array();

        $this->useIndex         = array();
        $this->where            = array();
        $this->groupBy          = array();
        $this->orderBy          = array();
        $this->limit            = NULL;

        $this->postFilters      = array();

        $this->map              = $this->entity->getMap();
        $this->db               = \Phidias\DB::connect($this->map->getDB());

        $this->iterator         = NULL;

        $this->unitOfWork       = NULL;
        $this->updateValues     = array();

        $this->joinAsInner      = FALSE;
        $this->relationAlias    = NULL;
    }

    public function setAlias($alias, $recursively = FALSE)
    {
        $this->alias = $alias;

        if ($recursively) {
            foreach ($this->joins as $joinName => $join) {
                $join['collection']->setAlias("$alias.$joinName", TRUE);
            }
        }
    }

    public function addPostFilter($filter)
    {
        $this->postFilters[] = $filter;

        return $this;
    }

    public function notEmpty()
    {
        $this->joinAsInner = TRUE;

        return $this;
    }

    public function relatedWith($alias)
    {
        $this->relationAlias = $alias;

        return $this;
    }

    public function iterator($iterator)
    {
        $this->iterator = $iterator;

        return $this;
    }

    public function attr($name, $origin = NULL)
    {
        if ($origin instanceof Collection) {
            $this->nest($name, $origin->joinAsInner ? 'inner' : 'left', $origin, $origin->relationAlias);
        } else {
            $this->attributes[$name] = $origin;
        }

        return $this;
    }

    public function attrs()
    {
        foreach (func_get_args() as $attr) {
            $this->attr($attr);
        }

        return $this;
    }

    public function allAttributes()
    {
        foreach (array_keys($this->map->getAttributes()) as $attributeName) {
            $this->attr($attributeName);
        }

        return $this;
    }

    public function whereKey($keyValue)
    {
        $keyValue           = (array)$keyValue;

        foreach ($this->map->getKeys() as $index => $attributeName) {
            if (!isset($keyValue[$index])) {
                continue;
            }
            $this->where("$this->alias.$attributeName = :v", array('v' => $keyValue[$index]));
        }
    }

    public function where($condition, $parameters = NULL)
    {
        $this->where[] = $parameters ? $this->db->bindParameters($condition, $parameters) : $condition;

        return $this;
    }

    public function groupBy($group, $parameters = NULL)
    {
        $this->groupBy[] = $parameters ? $this->db->bindParameters($group, $parameters) : $group;

        return $this;
    }

    public function orderBy($order, $parameters = NULL)
    {
        $this->orderBy[] = $parameters ? $this->db->bindParameters($order, $parameters) : $order;

        return $this;
    }

    public function limit($limit)
    {
        $this->limit = $limit;
        return $this;
    }

    public function match($attributeName, $value = NULL, $mongoOperator = '&eq')
    {
        if (is_object($attributeName)) {
            $this->matchObject($attributeName);
            return $this;
        }

        if ($value === NULL) {

            $this->where("$attributeName IS NULL");

        } else if (is_scalar($value)) {

            $queryOperator = Operator::getSQLOperator($mongoOperator);
            $this->where("$attributeName $queryOperator :value", array('value' => $value));

        } else if (is_array($value)) {

            $targetArray = $this->normalizeArray($value);
            if ($targetArray) {
                $operator = $mongoOperator == '&nin' ? 'NOT IN' : 'IN';
                $this->where("$attributeName $operator :value", array('value' => $targetArray));
            }

        } else if ($value instanceof Entity && ($primaryKeyValue = $value->getPrimaryKeyValue()) && count($primaryKeyValue) == 1) {

            $queryOperator = Operator::getSQLOperator($mongoOperator);
            $this->where("$attributeName $queryOperator :value", array('value' => current($primaryKeyValue)));

        }

        return $this;
    }

    private function normalizeArray($array)
    {
        $targetArray = array();

        foreach ($array as $element) {
            if ($element instanceof Entity && ($primaryKeyValue = $element->getPrimaryKeyValue()) &&  count($primaryKeyValue) == 1) {
                $targetArray[] = current($primaryKeyValue);
            } else if (is_scalar($element)) {
                $targetArray[] = $element;
            }
        }

        return $targetArray;
    }

    public function search($query, $attributes)
    {
        if ($query === NULL || !trim($query)) {
            return $this;
        }

        $attributes = (array)$attributes;

        $words = explode(' ', trim($query));
        foreach ($words as $word) {
            if (!$word = trim($word)) {
                continue;
            }
            $word = str_replace('%', '\%', $word);

            $matchingConditions = array();
            foreach ($attributes as $attributeName) {
                $matchingConditions[] = "$attributeName LIKE :word";
            }
            $matchingCondition = '(' . implode(' OR ', $matchingConditions) . ')';
            $this->where($matchingCondition, array('word' => "%$word%"));
        }

        return $this;
    }

    private function matchObject($object)
    {
        $localFilter = new \stdClass;

        foreach ($object as $attributeName => $value) {

            if (isset($this->joins[$attributeName]) && is_object($value) && !($value instanceof Entity && $value->getPrimaryKeyValue()) ) {
                $this->joins[$attributeName]['collection']->matchObject($value);
            } else {

                if ($value === NULL || (is_scalar($value) && trim($value) === '')) {
                    continue;
                }

                if (Operator::isOperator($value)) {
                    $this->match("$this->alias.$attributeName", Operator::getValue($value), Operator::getOperator($value));
                } else {
                    $this->match("$this->alias.$attributeName", $value);
                }

            }
        }

        return $this;
    }

    public function useIndex($index)
    {
        $this->useIndex[] = $index;

        return $this;
    }

    public function join($name, $localColumn, $foreignColumn, $collection, $type = 'inner')
    {
        $collection->setAlias("$this->alias.$name", TRUE);

        $this->joins[$name] = array(
            'type'          => $type,
            'collection'    => $collection,
            'localColumn'   => $localColumn,
            'foreignColumn' => $foreignColumn
        );

        return $this;
    }


    private function nest($name, $type, $collection, $relationIdentifier = NULL, $identifierIsLocal = NULL)
    {
        $localMap   = $this->entity->getMap();
        $remoteMap  = $collection->entity->getMap();

        /* Attempt to deduce the relation */


        /* 1. By join name*/
        if ($relationIdentifier === NULL) {
            if ($localMap->getRelation($name) !== NULL) {
                $relationIdentifier = $name;
                $identifierIsLocal  = TRUE;
            }
        }

        /* 2. If a single relation to the entity exists */
        if ($relationIdentifier === NULL) {

            $outgoingRelations = $localMap->getRelations($collection->entity);
            if (count($outgoingRelations) == 1) {
                $relationNames      = array_keys($outgoingRelations);
                $relationIdentifier = array_pop($relationNames);
                $identifierIsLocal  = TRUE;
            } else {
                $incomingRelations = $remoteMap->getRelations($this->entity);
                if (count($incomingRelations) == 1) {
                    $relationNames      = array_keys($incomingRelations);
                    $relationIdentifier = array_pop($relationNames);
                    $identifierIsLocal  = FALSE;
                }
            }

        }

        if ($relationIdentifier == NULL) {
            $localClass  = get_class($this->entity);
            $remoteClass = get_class($collection->entity);
            trigger_error("Could not determine relation from '$localClass' to '$remoteClass'", E_USER_ERROR);
        }


        $relation = $identifierIsLocal ? $localMap->getRelation($relationIdentifier) : $remoteMap->getRelation($relationIdentifier);
        if ($relation == NULL) {
            trigger_error("Relation '$relationIdentifier' not found", E_USER_ERROR);
        }

        if ($identifierIsLocal) {
            $toAttribute    = $remoteMap->getAttribute($relation['attribute']);
            $localColumn    = $relation['column'];
            $foreignColumn  = $toAttribute['column'];
        } else {
            $fromAttribute  = $localMap->getAttribute($relation['attribute']);
            $localColumn    = $fromAttribute['column'];
            $foreignColumn  = $relation['column'];
        }

        $this->join($name, $localColumn, $foreignColumn, $collection, $type);

        return $this;
    }

    private function buildAliasMap()
    {
        $retval = array();

        $mapAttributes = $this->map->getAttributes();
        foreach ($mapAttributes as $attributeName => $attributeData) {
            $retval["$this->alias.$attributeName"] = '`'.$this->alias.'`.`'.$attributeData['column'].'`';
        }

        foreach ($this->joins as $name => $join) {
            $retval = array_merge($retval, $join['collection']->buildAliasMap());
        }


        /* Derived attributes */
        foreach ($this->attributes as $attributeName => $attributeSource) {
            if (!isset($mapAttributes[$attributeName]) && $attributeSource != "NULL") {
                $retval["$this->alias.$attributeName"] = '('.$this->translate($attributeSource, $retval).')';
            }
        }

        return $retval;
    }

    private function translate($string, $aliasMap)
    {
        return strtr($string, $aliasMap);
    }

    public function getSelect($aliasMap = NULL)
    {
        if ($aliasMap == NULL) {
            $aliasMap = $this->buildAliasMap();
        }

        $select = new \Phidias\DB\Select($this->map->getTable(), $this->alias);

        /* Always select keys */
        foreach ($this->map->getKeys() as $keyAttributeName) {
            $select->field($this->alias.'.'.$keyAttributeName, $this->translate($this->alias.'.'.$keyAttributeName, $aliasMap));
        }

        foreach ($this->attributes as $name => $origin) {
            if ($origin == NULL) {
                $origin = $this->alias.'.'.$name;
            }
            $select->field($this->alias.'.'.$name, $this->translate($origin, $aliasMap));
        }

        foreach ($this->where as $condition) {
            $select->where($this->translate($condition, $aliasMap));
        }

        foreach ($this->groupBy as $group) {
            $select->groupBy($this->translate($group, $aliasMap));
        }

        foreach ($this->orderBy as $order) {
            $select->orderBy($this->translate($order, $aliasMap));
        }

        foreach ($this->useIndex as $index) {
            $select->useIndex($index);
        }

        foreach ($this->joins as $name => $join) {
            $conditions = array("`$this->alias`.`{$join['localColumn']}` = `$this->alias.$name`.`{$join['foreignColumn']}`");

            foreach ($join['collection']->where as $condition) {
                $conditions[] = $this->translate($condition, $aliasMap);
            }

            $nestedCollection = clone($join['collection']);
            $nestedCollection->where = array();
            $nestedSelect = $nestedCollection->getSelect($aliasMap);

            $select->join($join['type'], $nestedSelect, $conditions);
        }

        if ($this->limit) {
            $select->limit($this->limit);
        }

        return $select;
    }

    private function buildIterator()
    {
        $key = array();
        foreach ($this->map->getKeys() as $attributeName) {
            $key[] = "$this->alias.$attributeName";
        }

        $iterator = new \Phidias\DB\Iterator(get_class($this->entity), $key, $this->hasOneElement);

        foreach (array_keys($this->attributes) as $attributeName) {
            $iterator->attr($attributeName, "$this->alias.$attributeName");
        }

        foreach ($this->joins as $attributeName => $joinData) {
            $iterator->attr($attributeName, $joinData['collection']->buildIterator());
        }

        return $iterator;
    }

    public function find($primaryKeyValue = NULL)
    {
        if ($this->hasOneElement) {
            $this->limit(1);
        }

        if ($primaryKeyValue !== NULL) {
            $this->whereKey($primaryKeyValue);
        }

        $resultSet  = $this->db->select($this->getSelect());
        $iterator   = $this->iterator == NULL ? $this->buildIterator() : $this->iterator;
        $iterator->setResultSet($resultSet);

        if ($primaryKeyValue) {
            $iterator = $iterator->first();
            if ($iterator === NULL) {
                throw new Exception\EntityNotFound(get_class($this->entity), implode(', ', (array)$primaryKeyValue));
            }
        } else if ($this->hasOneElement) {
            return $iterator->first();
        }

        foreach ($this->postFilters as $filter) {
            $iterator->addPostFilter($filter);
        }

        return $iterator;
    }

    public function count()
    {
        $select = $this->getSelect();

        return $this->db->count($select);
    }



    /* Unions and intersections */
    public function union($collection)
    {
        /* An union with a whole collection is always a whole collection */
        if (!$this->where || !$collection->where) {
            $this->where = array();

            return $this;
        }

        $newConditions      = array();
        $newConditions[]    = '('.implode(' AND ', $this->where).')';
        $newConditions[]    = '('.implode(' AND ', $collection->where).')';

        $this->where        = array('('.implode(' OR ', $newConditions).')');

        return $this;
    }

    public function intersect($collection)
    {
        /* And intersection with a whole collection is always the delimited collection */
        if (!$collection->where) {
            return $this;
        }

        if (!$this->where) {
            return $collection;
        }

        $newConditions      = array();
        $newConditions[]    = '('.implode(' AND ', $this->where).')';
        $newConditions[]    = '('.implode(' AND ', $collection->where).')';

        $this->where        = array('('.implode(' AND ', $newConditions).')');

        return $this;
    }




    /* Unit of work functions */
    public function add($entity)
    {
        if ($this->unitOfWork === NULL) {
            $this->unitOfWork = new Collection\UnitOfWork($this->attributes, $this->joins, $this->map, $this->db);
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
            $columnName = $this->map->getColumn($attributeName);
            if (isset($this->joins[$attributeName])) {

                try {
                    $values[$columnName] = isset($entity->$attributeName) ? $this->joins[$attributeName]['collection']->save($entity->$attributeName) : NULL;
                } catch (\Phidias\DB\Exception\DuplicateKey $e) {
                    $values[$columnName] = $e->getKey() == 'PRIMARY' ? $e->getEntry() : NULL;
                }

            } else if (isset($entity->$attributeName)) {
                $values[$columnName] = $entity->$attributeName;
            }
        }

        $entityID = $entity->getID();
        if ($entityID) {

            $entityID       = (array)$entityID;
            $idConditions   = array();
            $idValues       = array();

            foreach ($this->map->getKeys() as $index => $attributeName) {
                $columnName                 = $this->map->getColumn($attributeName);
                $idConditions[]             = "$columnName = :$attributeName";
                $idValues[$attributeName]   = isset($entityID[$index]) ? $entityID[$index] : NULL;
            }
            $this->db->update($this->map->getTable(), $values, implode(' AND ', $idConditions), $idValues);

        } else {
            $this->db->insert($this->map->getTable(), $values);
        }

        $newID = array();
        foreach ($this->map->getKeys() as $attributeName) {
            if (isset($entity->$attributeName)) {
                $newID[] = $entity->$attributeName;
            } else {
                $newID[] = $this->db->getInsertID();
            }
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

        if (!$this->map->hasAttribute($attributeName)) {
            return $this;
        }

        $this->updateValues[$this->map->getColumn($attributeName)] = $value;

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

        return $this->db->update($this->map->getTable().' `'.$this->alias.'`', $this->updateValues, $updateCondition);
    }

    public function delete($entity = NULL)
    {
        if ($entity !== NULL) {
            $this->whereKey($entity->getID());
        }

        if (!$this->where) {
            trigger_error("attempt to delete ignored because no conditions are defined.  If you wish to delete the entire collection use the conditional where(1)");
            return 0;
        }

        $aliasMap           = $this->buildAliasMap();
        $deleteConditions   = array();
        foreach($this->where as $where) {
            $deleteConditions[] = $this->translate($where, $aliasMap);
        }

        /* Since MySQL does not support "DELETE FROM table a WHERE a.some = thing" */
        $deleteCondition = str_replace("`$this->alias`.", '', implode(' AND ', $deleteConditions));

        return $this->db->delete($this->map->getTable(), $deleteCondition);
    }

    public function getInsertID()
    {
        return $this->db->getInsertID();
    }

}