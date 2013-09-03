<?php
namespace Phidias\ORM\DB;

use Phidias\ORM\DB;
use Phidias\Core\Debug;

class Table
{
    private $_schema;
    private $_db;

    public function __construct($schema, $db)
    {
        $this->_schema      = $schema;
        $this->_db          = $db;
    }

    public function create($engine = 'InnoDB')
    {
        $query = "CREATE TABLE IF NOT EXISTS `{$this->_schema['table']}` ( \n";

        $fields = isset($this->_schema['keys']) ? $this->_schema['keys'] : array();
        if ( isset($this->_schema['attributes']) ) {
            $fields = array_merge($fields, $this->_schema['attributes']);
        }

        /* Find foreign fields */
        $foreignKeys = array();
        if ( isset($this->_schema['relations']) ) {
            foreach ( $this->_schema['relations'] as $relationName => $relationData ) {

                $foreignEntity  = $relationData['entity'];
                $foreignSchema  = $foreignEntity::getSchema();

                $firstKey           = reset($foreignSchema['keys']);
                $firstKey['column'] = isset($relationData['column']) ? $relationData['column'] : $relationName;
                $firstKey['table']  = $foreignSchema['table'];
                unset($firstKey['autoIncrement']);

                $fields[$firstKey['column']] = $firstKey;
                $foreignKeys[$relationName] = $firstKey;
            }
        }

        foreach ( $fields as $fieldName => $fieldData ) {
            $columnName         = isset($fieldData['column']) ? $fieldData['column'] : $fieldName;
            $columnType         = isset($fieldData['type']) ? $fieldData['type'] : 'VARCHAR';
            $columnLength       = isset($fieldData['length']) ? $fieldData['length'] : NULL;
            $columnUnsigned     = isset($fieldData['unsigned']) ? 'unsigned' : '';
            $columnNull         = isset($fieldData['null']) && $fieldData['null'] ? 'NULL' : 'NOT NULL';
            $columnIncrement    = isset($fieldData['autoIncrement']) ? $fieldData['autoIncrement'] : FALSE;


            if ( !isset($fieldData['default']) && $columnNull == 'NULL' ) {
                $fieldData['default'] = 'NULL';
            }

            $columnDefault      = isset($fieldData['default']) ? "DEFAULT {$fieldData['default']}" : '';

            $query .= "\t`$columnName` "
                        .($columnLength ? "$columnType($columnLength)" : $columnType )." "
                        ."$columnUnsigned "
                        ."$columnNull "
                        ."$columnDefault "
                        .($columnIncrement ? "AUTO_INCREMENT" : '')
                        .", \n";
        }

        $keyNames = array();
        foreach ( $this->_schema['keys'] as $fieldName => $fieldData ) {
            $keyNames[] = isset($fieldData['column']) ? $fieldData['column'] : $fieldName;
        }
        $query .= "\tPRIMARY KEY (".implode(',', $keyNames).")";


        $constraintQueries = array();

        foreach ( $foreignKeys as $relationName => $fieldData ) {

            $columnName = isset($fieldData['column']) ? $fieldData['column'] : $relationName;
            $query .= ",\n\tKEY `$columnName` (`$columnName`)";

            $constraintQueries[] = "ALTER TABLE `{$this->_schema['table']}` ADD FOREIGN KEY ( `$columnName` ) REFERENCES `{$fieldData['table']}` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;";
        }
        $query .= "\n)  ENGINE=$engine;";

        $this->_db->query($query);

        foreach ( $constraintQueries as $constraintQuery ) {
            $this->_db->query($constraintQuery);
        }
    }

    public function truncate()
    {
        $table = $this->_schema['table'];
        $this->_db->query("DELETE FROM $table");
        $this->_db->query("ALTER TABLE $table AUTO_INCREMENT = 1");
    }

    public function drop()
    {
        $this->_db->query("DROP TABLE IF EXISTS {$this->_schema['table']}");
    }

}