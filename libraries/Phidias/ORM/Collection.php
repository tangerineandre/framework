<?php
namespace Phidias\ORM;

class Collection
{
    private $entity;
    private $hasOneElement;

    private $attributes;
    private $joins;

    private $where;
    private $orderBy;
    private $groupBy;
    private $limit;

    private $map;
    private $db;

    private $iterator;

    private $joinAsInner;

    /* DB Write functionality */
    private $unitOfWork;
    private $updateValues;


    public function __construct($entity, $hasOneElement = FALSE)
    {
        $this->entity           = $entity;
        $this->hasOneElement    = $hasOneElement;

        $this->attributes       = array();
        $this->joins            = array();

        $this->where            = array();
        $this->groupBy          = array();
        $this->orderBy          = array();
        $this->limit            = NULL;

        $this->map              = $this->entity->getMap();
        $this->db               = \Phidias\DB::connect($this->map->getDB());

        $this->iterator         = NULL;

        $this->unitOfWork       = NULL;
        $this->updateValues     = array();

        $this->joinAsInner      = FALSE;
    }

    public function notEmpty()
    {
        $this->joinAsInner = TRUE;

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
            $this->join($name, $origin->joinAsInner ? 'inner' : 'left', $origin);
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
        $collectionAlias    = get_class($this->entity);

        foreach ($this->map->getKeys() as $index => $attributeName) {
            if (!isset($keyValue[$index])) {
                continue;
            }
            $this->where("$collectionAlias.$attributeName = :v", array('v' => $keyValue[$index]));
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

    public function equals($attributeName, $value)
    {
        if ( $value === NULL ) {
            $this->where("$attributeName IS NULL");
        } else {
            $this->where("$attributeName = :value", array('value' => $value));
        }

        return $this;
    }

    public function like($attribute, $query)
    {
        $words = explode(' ', trim($query));
        foreach ($words as $word) {
            if (!$word = trim($word)) {
                continue;
            }
            $word = str_replace('%', '\%', $word);
            $this->where("$attribute LIKE :word", array('word' => "%$word%"));
        }

        return $this;
    }


    private function join($name, $type, $collection, $relationIdentifier = NULL, $identifierIsLocal = NULL)
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

        $this->joins[$name] = array(
            'type'          => $type,
            'collection'    => $collection,
            'localColumn'   => $localColumn,
            'foreignColumn' => $foreignColumn
        );

        return $this;
    }

    private function buildAliasMap($alias)
    {
        $retval = array();
        foreach ($this->map->getAttributes() as $attributeName => $attributeData) {
            $retval["$alias.$attributeName"] = '`'.$alias.'`.`'.$attributeData['column'].'`';
        }

        foreach ($this->joins as $name => $join) {
            $retval = array_merge($retval, $join['collection']->buildAliasMap("$alias.$name"));
        }

        return $retval;
    }

    private function translate($string, $aliasMap)
    {
        return strtr($string, $aliasMap);
    }

    private function buildSelect($alias = NULL, $aliasMap = NULL)
    {
        if ($alias == NULL) {
            $alias = get_class($this->entity);
        }

        if ($aliasMap == NULL) {
            $aliasMap = $this->buildAliasMap($alias);
        }

        $select = new \Phidias\DB\Select($this->map->getTable(), $alias);

        /* Always select keys */
        foreach ($this->map->getKeys() as $keyAttributeName) {
            $select->field($alias.'.'.$keyAttributeName, $this->translate($alias.'.'.$keyAttributeName, $aliasMap));
        }

        foreach ($this->attributes as $name => $origin) {
            if ($origin == NULL) {
                $origin = $alias.'.'.$name;
            }
            $select->field($alias.'.'.$name, $this->translate($origin, $aliasMap));
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

        foreach ($this->joins as $name => $join) {
            $condition = "`$alias`.`{$join['localColumn']}` = `$alias.$name`.`{$join['foreignColumn']}`";
            $select->join($join['type'], $join['collection']->buildSelect("$alias.$name", $aliasMap), $condition);
        }

        if ($this->limit) {
            $select->limit($this->limit);
        }

        return $select;
    }

    private function buildIterator($alias = NULL)
    {
        if ($alias === NULL) {
            $alias = get_class($this->entity);
        }

        $key = array();
        foreach ($this->map->getKeys() as $attributeName) {
            $key[] = "$alias.$attributeName";
        }

        $iterator = new \Phidias\DB\Iterator(get_class($this->entity), $key, $this->hasOneElement);

        foreach (array_keys($this->attributes) as $attributeName) {
            $iterator->attr($attributeName, "$alias.$attributeName");
        }

        foreach ($this->joins as $attributeName => $joinData) {
            $iterator->attr($attributeName, $joinData['collection']->buildIterator("$alias.$attributeName"));
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

        $resultSet  = $this->db->select($this->buildSelect());
        $iterator   = $this->iterator == NULL ? $this->buildIterator() : $this->iterator;
        $iterator->setResultSet($resultSet);

        if ($primaryKeyValue) {
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

            } else {
                $values[$columnName] = isset($entity->$attributeName) ? $entity->$attributeName : NULL;
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

        return $this->db->update($this->map->getTable().' '.$this->alias, $this->updateValues, $updateCondition);
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

        $alias              = get_class($this->entity);
        $aliasMap           = $this->buildAliasMap($alias);
        $deleteConditions   = array();
        foreach($this->where as $where) {
            $deleteConditions[] = $this->translate($where, $aliasMap);
        }

        /* Since MySQL does not support "DELETE FROM table a WHERE a.some = thing" */
        $deleteCondition = str_replace("`$alias`.", '', implode(' AND ', $deleteConditions));

        return $this->db->delete($this->map->getTable(), $deleteCondition);
    }

    public function getInsertID()
    {
        return $this->db->getInsertID();
    }


}