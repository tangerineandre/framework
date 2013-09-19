<?php
/*
 * Dataset
 *
 * El dataset itera sobre un RESULT SET interpretando cada registro como un OBJECTO de clase CLASSNAME.
 * Establece una serie de ATRIBUTOS a asociar con ese objeto.
 *
 * Poporciona tambien las funciones para constuir el RESULT SET (i.e. construye y ejecuta el query respectivo)
 *
 */
namespace Phidias\ORM;

use Phidias\ORM\DB;
use Phidias\ORM\DB\Query;
use Phidias\ORM\Dataset\Iterator;

class Dataset
{
    const TYPE_COLLECTION   = 0;
    const TYPE_SINGLE       = 1;

    /* The basic collection tree */
    protected $_className;
    protected $_returnType;

    private $_attributes;
    private $_nested;
    private $_where;
    private $_limit;
    private $_order;

    /* Data for creating the query and obtaining the resultSet */
    private $_resultSet;
    private $_schema;

    /* Data necessary for interpreting the results */
    private $_tableAlias;

    /* Data filters (to be applied when fetching object attributes) */
    private $_filters;

    public function __construct($className = NULL)
    {
        $this->_className       = $className === NULL ? get_called_class() : $className;

        $this->_attributes      = array();
        $this->_nested          = array();
        $this->_where           = array();

        $this->_resultSet       = NULL;
    }

    public function getSchema()
    {
        if ($this->_schema === NULL) {
            $className = $this->_className;
            $this->_schema = $className::$_schema;
        }

        return $this->_schema;
    }

    public function setFilters($filters)
    {
        $this->_filters = $filters;
    }

    public function filter($filters = NULL, $function = NULL)
    {
        if ($filters === NULL) {
            $this->_filters = $filters;
            return $this;
        }

        if ($this->_filters === NULL) {
            $this->_filters = array();
        }

        if (is_array($filters)) {
            $this->_filters = array_merge($this->_filters, $filters);
        } else if ($function) {
            $this->_filters[$filters] = $function;
        }

        return $this;
    }

    public function getClassName()
    {
        return $this->_className;
    }

    public function getAttributes()
    {
        return $this->_attributes;
    }

    public function getTableAlias()
    {
        if ( $this->_tableAlias === NULL ) {
            $schema = $this->getSchema();
            $this->_tableAlias = Query::allocateAlias($schema['table']);
        }

        return $this->_tableAlias;
    }


    public function attr($attributeName, $attributeSource = NULL, $parameters = NULL)
    {
        if ($attributeSource === NULL) {
            $this->_attributes[$attributeName] = NULL;
            return $this;
        }

        /* Nesting */
        if ($attributeSource instanceof Dataset) {
            $this->_nested[$attributeName] = $attributeSource;
            return $this;
        }

        if (gettype($attributeSource) == 'string') {
            /* Bind parameters */
            if ( is_array($parameters) ) {
                $attributeSource = $this->_bindParameters($attributeSource, $parameters);
            }
        }

        $this->_attributes[$attributeName] = $attributeSource !== NULL ? $attributeSource : $attributeName;

        return $this;
    }

    public function attrs()
    {
        foreach (func_get_args() as $attributeName) {
            if (is_array($attributeName)) {
                foreach ($attributeName as $subAttributeName => $source) {
                    $this->attr($subAttributeName, $source);
                }
            } else {
                $this->attr($attributeName);
            }
        }
        return $this;
    }


    public function allAttributes()
    {
        $schema = $this->getSchema();

        if (isset($schema['keys'])) {
            $keys = array_keys($schema['keys']);
            foreach ($keys as $attributeName) {
                $this->attr($attributeName);
            }
        }

        if (isset($schema['attributes'])) {
            $attributes = array_keys($schema['attributes']);
            foreach ($attributes as $attributeName) {
                $this->attr($attributeName);
            }
        }

        if (isset($schema['relations'])) {
            $attributes = array_keys($schema['relations']);
            foreach ($attributes as $attributeName) {
                $this->attr($attributeName);
            }
        }

        return $this;
    }



    public function where($stringCondition, $parameters = NULL)
    {
        /* Bind parameters */
        if ( is_array($parameters) ) {
            $stringCondition = $this->_bindParameters($stringCondition, $parameters);
        }
        $this->_where[] = $stringCondition;

        return $this;
    }

    public function equals($attributeName, $value)
    {
        if ( $value === NULL ) {
            $condition = $attributeName.' IS NULL';
        } else if (gettype($value) == 'string') {
            $condition = $attributeName." = '".DB::escapeString($value)."'";
        } else {
            $condition = $attributeName.' = '.$value;
        }
        $this->_where[] = $condition;

        return $this;
    }

    public function limit($value)
    {
        $this->_limit = $value;
        return $this;
    }

    public function orderBy($value)
    {
        $this->_order = $value;
        return $this;
    }

    public function count()
    {
        $query = $this->_buildQuery();
        $query->limit(NULL);
        $query->orderBy(NULL);
        $query->clearSelect();
        $query->selectColumn('count', 'COUNT(*)');

        $className  = $this->_className;
        $resultSet  = $className::getDB()->query($query->toSQL());

        $retval = $resultSet->fetch_assoc();
        return isset($retval['count']) ? $retval['count'] : NULL;
    }

    public function find()
    {
        $iterator = new Iterator($this);
        return $this->_returnType == self::TYPE_COLLECTION ? $iterator : $iterator->first();
    }

    private function _bindParameters($string, $parameters)
    {
        $keys           = array();
        $cleanValues    = array();
        foreach ( $parameters as $key => $value ) {
            $keys[] = ":$key";

            switch ( gettype($value) ) {
                case 'string':
                    $cleanValues[] = "'".DB::escapeString($value)."'";
                break;

                case 'boolean':
                    $cleanValues[] = $value ? 1 : 0;
                break;

                default:
                    $cleanValues[] = DB::escapeString($value);
                break;
            }
        }

        return str_replace($keys, $cleanValues, $string);
    }


    /* Functions to iterate over the result set */
    public function toObject($row, $pointer, $resultSet)
    {
        $schema             = $this->getSchema();
        $returnClassName    = $this->_className;
        $returnObject       = new $returnClassName;

        foreach ( array_keys($this->_attributes) as $attributeName ) {
            $sourceColumn = $this->_tableAlias.'_'.$attributeName;

            if (isset($this->_filters[$attributeName])) {
                $returnObject->$attributeName = $this->_filters[$attributeName]($row[$sourceColumn]);
            } else {
                $returnObject->$attributeName = $row[$sourceColumn];
            }
        }

        /* Force fetch IDs and build restrictions */
        $restrictions = array();
        foreach (array_keys($schema['keys']) as $attributeName) {
            $sourceColumn = $this->_tableAlias.'_'.$attributeName;
            $returnObject->$attributeName = $row[$sourceColumn];

            $restrictions[] = array(
                'column'    => $sourceColumn,
                'value'     => $row[$sourceColumn]
            );
        }

        foreach ( $this->_nested as $attributeName => $nestedDataset ) {
            $iterator = new Iterator($nestedDataset, $resultSet, $pointer, $restrictions );
            $returnObject->$attributeName = $nestedDataset->_returnType == self::TYPE_SINGLE ? $iterator->first() : $iterator;
        }

        return $returnObject;
    }

    /* Data for creating the query and obtaining the resultSet */
    public function getResultSet()
    {
        if ( $this->_resultSet === NULL ) {
            $query              = $this->_buildQuery()->toSQL();
            $className          = $this->_className;
            $this->_resultSet   = $className::getDB()->query($query);
        }

        return $this->_resultSet;
    }

    private function _buildQuery($aliasMap = NULL)
    {
        $schema = $this->getSchema();
        if ( $aliasMap === NULL ) {
            $aliasMap = $this->_buildAliasMap();
        }

        $query = new Query($schema['table'], $this->getTableAlias());
        $query->limit($this->_limit);
        $query->orderBy($this->_translate($this->_order, $aliasMap));

        foreach ($this->_attributes as $attributeName => $attributeSource) {
            if ( $attributeSource === NULL ) {
                $column = isset($schema['attributes'][$attributeName]['column']) ? $schema['attributes'][$attributeName]['column'] : $attributeName;
                $attributeSource = $this->_tableAlias.'.'.$column;
            } else {
                $attributeSource = $this->_translate($attributeSource, $aliasMap);
            }
            $query->selectColumn($this->_tableAlias.'_'.$attributeName, $attributeSource);
        }

        /* Force IDs to be selected */
        foreach ($schema['keys'] as $attributeName => $attributeData) {
            $column = isset($attributeData['column']) ? $attributeData['column'] : $attributeName;
            $query->selectColumn($this->_tableAlias.'_'.$attributeName, $this->_tableAlias.'.'.$column);
        }

        /* Join nested */
        foreach ($this->_nested as $attributeName => $nestedDataset) {

            $nestedSchema = $nestedDataset->getSchema();
            $nestingData = NULL;

            if ( isset($nestedSchema['relations']) ) {
                foreach ($nestedSchema['relations'] as $relationName => $relationData) {
                    if ( $relationData['entity'] == $this->_className ) {

                        $keys = array_keys($schema['keys']);
                        $nestingData = array(
                            'table'         => $schema['table'],
                            'key'           => $keys[0],
                            'alias'         => $this->getTableAlias(),
                            'column'        => isset($relationData['column']) ? $relationData['column'] : $relationName,
                            'foreignAlias'  => $nestedDataset->getTableAlias()
                        );
                        break;
                    }
                }
            }

            if ( $nestingData === NULL && isset($schema['relations']) ) {
                foreach ($schema['relations'] as $relationName => $relationData) {
                    if ( $relationData['entity'] == $nestedDataset->_className && $relationName == $attributeName ) {

                        $keys = array_keys($nestedSchema['keys']);
                        $nestingData = array(
                            'table'         => $nestedSchema['table'],
                            'key'           => $keys[0],
                            'alias'         => $nestedDataset->getTableAlias(),
                            'column'        => isset($relationData['column']) ? $relationData['column'] : $relationName,
                            'foreignAlias'  => $this->getTableAlias()
                        );
                        break;
                    }
                }
            }

            if ( $nestingData !== NULL ) {

                $childQuery = $nestedDataset->_buildQuery($aliasMap);

                if ( is_array($nestingData['column']) ) {
                    $keyConditions[] = array();
                    foreach ($nestingData['column'] as $column) {
                        $keyConditions[] = $nestingData['alias'].'.'.$nestingData['key'].' = '.$nestingData['foreignAlias'].".".$column;
                    }
                    $keyCondition = implode(' AND ', $keyConditions);
                } else {
                    $keyCondition = $nestingData['alias'].'.'.$nestingData['key'].' = '.$nestingData['foreignAlias'].".".$nestingData['column'];
                }

                $query->leftJoin($nestedSchema['table'], $nestedDataset->getTableAlias(), $keyCondition, $childQuery->getConditions());

                $query->merge($childQuery);

                $query->orderAdd($nestedDataset->_order);

            } else {
                trigger_error("No relation defined between {$this->_className} and {$nestedDataset->_className}", E_USER_WARNING);
            }

        }

        if ( count($this->_where) ) {
            foreach ( $this->_where as $condition ) {
                $query->addCondition($this->_translate($condition, $aliasMap));
            }
        }

        return $query;
    }

    private function _translate($string, $aliasMap = NULL)
    {
        if ( $string === NULL ) {
            return NULL;
        }

        if ( $aliasMap === NULL ) {
            $aliasMap = $this->_buildAliasMap();
        }

        return str_replace(array_keys($aliasMap), $aliasMap, $string);
    }

    private function _buildAliasMap($identifier = NULL, &$retval = NULL)
    {
        $schema = $this->getSchema();

        if ( $identifier === NULL ) {
            $identifier = basename($this->_className);
        }

        foreach ( $this->_nested as $attributeName => $nestedDataset ) {
            $nestedDataset->_buildAliasMap($identifier.'.'.$attributeName, $retval);
        }

        if ( $retval === NULL ) {
            $retval = array();
        }

        if ( isset($schema['keys']) ) {
            foreach ( $schema['keys'] as $attributeName => $attributeData ) {
                $retval[$identifier.".".$attributeName] = $this->getTableAlias().'.'.(isset($attributeData['column']) ? $attributeData['column'] : $attributeName);
            }
        }

        if ( isset($schema['attributes']) ) {
            foreach ( $schema['attributes'] as $attributeName => $attributeData ) {
                $retval[$identifier.".".$attributeName] = $this->getTableAlias().'.'.(isset($attributeData['column']) ? $attributeData['column'] : $attributeName);
            }
        }

        if ( isset($schema['relations']) ) {
            foreach ( $schema['relations'] as $attributeName => $attributeData ) {

                $column = isset($attributeData['column']) ? $attributeData['column'] : $attributeName;

                if ( is_array($column) ) {
                    foreach ($column as $columnName) {
                        $retval[$identifier.".".$attributeName] = $this->getTableAlias().'.'.$columnName;
                    }
                } else {
                    $retval[$identifier.".".$attributeName] = $this->getTableAlias().'.'.$column;
                }
            }
        }

        /* also, add aliases for custom attributes */
        $customAttributes = array();
        foreach ($this->getAttributes() as $attributeName => $attributeSource) {
            if ($attributeSource) {
                $retval[$identifier.".".$attributeName] = $this->_translate($attributeSource, $retval);
            }
        }

        return $retval;
    }

}