<?php
namespace Phidias\ORM;

class Collection
{
    private $alias;

    private $entity;
    private $hasOneElement;

    private $workingAttributes;
    private $joins;

    private $where;
    private $orderBy;
    private $groupBy;
    private $having;

    /* Paging data */
    private $limit;
    private $offset;
    private $page;

    private $postFilters;
    private $preFilters;

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
        $this->alias             = $this->generateAlias($entity);
        
        $this->entity            = $entity;
        $this->hasOneElement     = $hasOneElement;

        $this->workingAttributes = array();
        $this->joins             = array();

        $this->useIndex          = array();
        $this->where             = array();
        $this->orderBy           = array();
        $this->groupBy           = array();
        $this->having            = array();
        $this->limit             = NULL;
        
        $this->postFilters       = array();
        $this->preFilters        = array();
        
        $this->map               = $this->entity->getMap();
        $this->db                = \Phidias\DB::connect($this->map->getDB());
        
        $this->iterator          = NULL;
        
        $this->unitOfWork        = NULL;
        $this->updateValues      = array();
        
        $this->joinAsInner       = FALSE;
        $this->relationAlias     = NULL;
    }


    private function generateAlias($entity)
    {
        $class = get_class($entity);
        $parts = explode("\\", $class);

        $count = count($parts);

        if ($count >=2 && $parts[$count-1] === 'Entity') {
            return $parts[$count-2];
        }

        return $class;
    }


    public function setAlias($alias, $recursively = FALSE)
    {
        $this->alias = $alias;

        if ($recursively) {
            foreach ($this->joins as $joinName => $join) {
                $join['collection']->setAlias("$alias.$joinName", TRUE);
            }
        }

        return $this;
    }

    public function addPreFilter($filter)
    {
        $this->preFilters[] = $filter;

        return $this;
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
        if ($origin === NULL) {
            $validAttributes = $this->map->getAttributes();
            if (!isset($validAttributes[$name])) {
                return $this;
            }
        }

        if ($origin instanceof Collection) {
            $this->nest($name, $origin->joinAsInner ? 'inner' : 'left', $origin, $origin->relationAlias);
        } else {
            $this->workingAttributes[$name] = $origin;
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

    public function getAttributes()
    {
        return array_keys($this->workingAttributes);
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

    public function orderBy($attribute, $descending = FALSE)
    {
        $validAttributes = $this->map->getAttributes();

        if (isset($validAttributes[$attribute])) {
            $sortString = $descending ? 'DESC' : 'ASC';
            $this->order("$this->alias.$attribute $sortString");
        } else {
            trigger_error("orderBy attribute '$attribute' not found", E_USER_WARNING);
        }

        return $this;
    }

    public function order($order, $parameters = NULL)
    {
        $this->orderBy[] = $parameters ? $this->db->bindParameters($order, $parameters) : $order;

        return $this;
    }

    public function limit($limit)
    {
        $this->limit = $limit !== null ? max(1, (int)$limit) : null;

        return $this;
    }

    public function having($condition, $parameters = NULL)
    {
        $this->having[] = $parameters ? $this->db->bindParameters($condition, $parameters) : $condition;

        return $this;
    }

    public function getLimit()
    {
        return $this->limit;
    }

    /*
    Set the result offset
    This will override the page to match the offset
    */
    public function offset($offset)
    {
        $this->offset = max(0, (int)$offset);
        $this->page   = $this->limit === null ? 1 : ( 1 + floor($this->offset / $this->limit) );

        return $this;
    }

    public function getOffset()
    {
        return $this->offset;
    }


    /*
    Set the page
    This will override the offset to match the page
    */
    public function page($page)
    {
        $this->page   = max(1, (int)$page);
        $this->offset = $this->limit === null ? 0 : $this->limit * ($this->page - 1);

        return $this;
    }

    public function getPage()
    {
        return $this->page === null ? 1 : $this->page;
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
        //Deep clone
        $matchableObject = clone($object);

        /* Apply pre-filters */
        foreach ($this->preFilters as $filter) {
            call_user_func_array($filter, array($matchableObject));
        }        

        $validAttributes = $this->map->getAttributes();

        $localFilter = new \stdClass;

        foreach ($matchableObject as $attributeName => $value) {

            if (isset($this->joins[$attributeName]) && is_object($value) && !($value instanceof Entity && $value->getPrimaryKeyValue()) ) {
                $this->joins[$attributeName]['collection']->matchObject($value);
            } else {

                if (!isset($validAttributes[$attributeName])) {
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

    public function getNested($name)
    {
        return isset($this->joins[$name]) ? $this->joins[$name]['collection'] : NULL;
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
        foreach ($this->workingAttributes as $attributeName => $attributeSource) {
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

        foreach ($this->workingAttributes as $name => $origin) {
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

        foreach ($this->having as $condition) {
            $select->having($this->translate($condition, $aliasMap));
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

        if ($this->limit !== null) {
            if ($this->offset !== null) {
                $select->limit($this->offset, $this->limit);
            } else {
                $select->limit($this->limit);
            }
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

        foreach (array_keys($this->workingAttributes) as $attributeName) {
            $iterator->attr($attributeName, "$this->alias.$attributeName");
        }

        foreach ($this->joins as $attributeName => $joinData) {
            $iterator->attr($attributeName, $joinData['collection']->buildIterator());
        }

        foreach ($this->postFilters as $filter) {
            $iterator->addPostFilter($filter);
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

        if ($primaryKeyValue !== NULL) {
            $iterator = $iterator->first();
            if ($iterator === NULL) {
                throw new Exception\EntityNotFound(array(
                    'class' => get_class($this->entity),
                    'key'   => $primaryKeyValue
                ));
            }
        } else if ($this->hasOneElement) {
            return $iterator->first();
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
        $this->consolidateConditions();
        $collection->consolidateConditions();

        $newConditions = array();

        if ($this->where) {
            $newConditions[] = '('.implode(' AND ', $this->where).')';
        }

        if ($collection->where) {
            $newConditions[] = '('.implode(' AND ', $collection->where).')';
        }

        $this->where = array('('.implode(' OR ', $newConditions).')');

        return $this;
    }

    public function intersect($collection)
    {
        $this->consolidateConditions();
        $collection->consolidateConditions();

        $newConditions = array();

        if ($this->where) {
            $newConditions[] = '('.implode(' AND ', $this->where).')';
        }

        if ($collection->where) {
            $newConditions[] = '('.implode(' AND ', $collection->where).')';
        }

        $this->where = $newConditions;

        return $this;
    }

    private function consolidateConditions()
    {
        foreach ($this->joins as $joinData) {
            $joinData['collection']->consolidateConditions();
            $this->where = array_merge($this->where, $joinData['collection']->where);
            $joinData['collection']->where = array();
        }
    }




    /* Unit of work functions */
    public function add($entity)
    {
        if ($this->unitOfWork === NULL) {
            $this->unitOfWork = new Collection\UnitOfWork($this->workingAttributes, $this->joins, $this->map, $this->db, $this->preFilters);
        }

        $this->unitOfWork->add($entity);

        return $this;
    }

    public function save($incomingEntity = NULL)
    {
        if ($incomingEntity === NULL) {
            return $this->unitOfWork === NULL ? NULL : $this->unitOfWork->save();
        }

        /* Saving a single entity:
         * Use this collection definition to determine
         * which attributes and nested entities should be
         * inserted or updated
         */
        $entity = clone($incomingEntity);

        /* Apply pre-filters */
        foreach ($this->preFilters as $filter) {
            call_user_func_array($filter, array($entity));
        }


        $mapAttributes = $this->map->getAttributes();
        
        $targetValues  = array();

        foreach (array_keys($this->workingAttributes) as $attributeName) {

            $columnName = $this->map->getColumn($attributeName);

            if (isset($this->joins[$attributeName])) {

                try {
                    $targetValues[$columnName] = isset($entity->$attributeName) ? $this->joins[$attributeName]['collection']->save($entity->$attributeName) : NULL;
                } catch (\Phidias\DB\Exception\DuplicateKey $e) {
                    $exceptionData             = $e->getData();
                    $targetValues[$columnName] = $exceptionData['key'] == 'PRIMARY' ? $exceptionData['entry'] : NULL;
                }

            } else if (isset($mapAttributes[$attributeName]) && property_exists($entity, $attributeName)) {
                $targetValues[$columnName] = $entity->$attributeName;
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

            $this->db->update($this->map->getTable(), $targetValues, implode(' AND ', $idConditions), $idValues);

        } else {
            $this->db->insert($this->map->getTable(), $targetValues);
        }

        $newID = array();
        foreach ($this->map->getKeys() as $attributeName) {
            if (isset($entity->$attributeName)) {
                $newID[] = $entity->$attributeName;
            } else {
                $newID[] = $this->db->getInsertID();
            }
        }
        $incomingEntity->setID($newID);

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
        if (!$this->where && !$force) {
            trigger_error("attempt to update ignored because no conditions are defined.  If you wish to update the entire collection invoke update(TRUE)");
            return 0;
        }

        if ($this->where) {
            $aliasMap           = $this->buildAliasMap();

            $updateConditions   = array();
            foreach($this->where as $where) {
                $updateConditions[] = $this->translate($where, $aliasMap);
            }
            $updateCondition = implode(' AND ', $updateConditions);
        } else {
            $updateCondition = NULL;
        }


        $table = $this->map->getTable().' `'.$this->alias.'`';

        foreach ($this->joins as $name => $join) {

            $joinConditions = array("`$this->alias`.`{$join['localColumn']}` = `$this->alias.$name`.`{$join['foreignColumn']}`");
            foreach ($join['collection']->where as $condition) {
                $joinConditions[] = $this->translate($condition, $aliasMap);
            }

            $joinTable = $join['collection']->map->getTable();
            $joinAlias = $join['collection']->alias;

            $table .= " JOIN $joinTable `$joinAlias` ON ".implode(" AND ", $joinConditions);

        }

        return $this->db->update($table, $this->updateValues, $updateCondition);
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