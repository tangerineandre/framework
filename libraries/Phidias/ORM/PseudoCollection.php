<?php
namespace Phidias\ORM;

use Phidias\ORM\DB\Table;

class PseudoCollection extends Dataset
{
    private $pseudoSchema;
    private $tableName;
    private $countFromSource;

    public function __construct($className, $pseudoSchema)
    {
        $this->pseudoSchema = $pseudoSchema;
        parent::__construct($className);
        $this->_returnType = Dataset::TYPE_COLLECTION;
    }

    public function count()
    {
        if ($this->countFromSource === TRUE) {
            $object         = new $this->_className;
            $schema         = $object->getSchema();
            $table          = $schema['table'];
            $className      = $this->_className;
            $resultSet      = $className::getDB()->query("SELECT COUNT(DISTINCT(record)) as count FROM $table");

            $retval = $resultSet->fetch_assoc();
            return isset($retval['count']) ? $retval['count'] : NULL;
        }

        return parent::count();
    }

    public function getSchema()
    {
        $this->pseudoSchema['table'] = $this->tableName !== NULL ? $this->tableName : $this->getTableSQL();
        return $this->pseudoSchema;
    }

    public function useTable($tableName)
    {
        $this->tableName = $tableName;
    }

    public function createTable($tableName)
    {
        $schema             = $this->getSchema();
        $schema['table']    = $tableName;
        $className          = $this->_className;
        $db                 = $className::getDB();

        $table              = new Table($schema, $db);
        $table->drop();
        $table->create('MyISAM');

        $db->query("DROP VIEW IF EXISTS `vw_$tableName`");
        $db->query("CREATE VIEW `vw_$tableName` AS (".$this->getTableSQL().")");
        $db->query("INSERT INTO $tableName SELECT * FROM vw_$tableName");
    }

    public function useView($viewName)
    {
        $className  = $this->_className;
        $db         = $className::getDB();

        $db->query("DROP VIEW IF EXISTS `$viewName`");
        $db->query("CREATE VIEW `$viewName` AS (".$this->getTableSQL().")");

        $this->tableName        = $viewName;
        $this->countFromSource  = TRUE;
    }

    public function getTableSQL()
    {
        $fields = array_keys($this->pseudoSchema['attributes']);

        $select = array();
        $joins  = array();

        $cont = 1;
        $base = array_shift($fields);

        $select[] = "field$cont.record";
        $select[] = "field$cont.value as $base";
        $cont++;

        foreach ($fields as $fieldName) {
            $select[]   = "field$cont.value as $fieldName";
            $joins[]    = "LEFT JOIN form_values field$cont ON field$cont.record = field1.record AND field$cont.field = '$fieldName'";
            $cont++;
        }

        $retval = '(SELECT '.implode(', ', $select)."\n";
        $retval .= 'FROM form_values field1'."\n";
        $retval .= implode(' ', $joins)."\n";
        $retval .= "WHERE field1.field = '$base')";

        return $retval;
    }
}