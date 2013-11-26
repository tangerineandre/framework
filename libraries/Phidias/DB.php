<?php
namespace Phidias;

use Phidias\Core\Configuration;
use Phidias\Core\Debug;

/*
 *
 * This class is a simple wrapper for PHPs mysqli extension.
 * It stores mysqli object instances for the specified identifiers
 *
 */
class DB
{
    private static $_instances = array();
    private $_mysqli;

    /**
     * Connects to the specified database
     *
     * @param string $identifier String identifier found in Phidias configuration
     * host: db.$identifier.host
     * username: db.$identifier.host
     * password: db.$identifier.host
     * database: db.$identifier.host
     * charset: db.$identifier.charset
     *
     * @param Array $identifier Array specifing host, username, password, database and charset in its keys
     *
     * @return DB DB instance
     */
    public static function connect($identifier = NULL)
    {
        if (is_array($identifier)) {
            $instanceKey = serialize($identifier);
        } elseif (is_string($identifier)) {
            $instanceKey = $identifier;
        } else {
            $instanceKey = 0;
        }

        if (isset(self::$_instances[$instanceKey])) {
            return self::$_instances[$instanceKey];
        }

        if ($identifier === NULL) {
            $host       = Configuration::get("db.host");
            $username   = Configuration::get("db.username");
            $password   = Configuration::get("db.password");
            $database   = Configuration::get("db.database");
            $charset    = Configuration::get("db.charset");
        } else if (is_string($identifier)) {
            $host       = Configuration::get("db.$identifier.host");
            $username   = Configuration::get("db.$identifier.username");
            $password   = Configuration::get("db.$identifier.password");
            $database   = Configuration::get("db.$identifier.database");
            $charset    = Configuration::get("db.$identifier.charset");
        } else if (is_array($identifier)) {
            $host       = isset($identifier['host'])        ? $identifier['host']       : NULL;
            $username   = isset($identifier['username'])    ? $identifier['username']   : NULL;
            $password   = isset($identifier['password'])    ? $identifier['password']   : NULL;
            $database   = isset($identifier['database'])    ? $identifier['database']   : NULL;
            $charset    = isset($identifier['charset'])     ? $identifier['charset']    : NULL;
        } else {
            throw new DB\Exception("invalid connection parameter");
        }

        Debug::startBlock("connecting to DB: $username:$password@$host/$database", 'SQL');
        $mysqli = new \mysqli($host, $username, $password, $database);

        if ($mysqli->connect_errno) {
            throw new DB\Exception\CannotConnect($mysqli->connect_error);
        }

        if ($charset) {
            $mysqli->set_charset($charset);
        }
        Debug::endBlock();

        self::$_instances[$instanceKey] = new DB($mysqli);
        return self::$_instances[$instanceKey];
    }

    public function __construct($mysqli)
    {
        $this->_mysqli = $mysqli;
    }

    public function query($query, $parameters = NULL)
    {
        if (is_array($parameters)) {
            $query = $this->bindParameters($query, $parameters);
        }

        Debug::startBlock(strlen($query) > 5120 ? '[Query too long to debug]' : $query, 'SQL');
        $result = $this->_mysqli->query($query);
        Debug::endBlock();

        if ($result === FALSE) {
            throw $this->obtainException($this->_mysqli->errno, $this->_mysqli->error);
        }

        return $result;
    }

    public function beginTransaction()
    {
        $this->_mysqli->autocommit(FALSE);
    }

    public function commit()
    {
        $this->_mysqli->commit();
        $this->_mysqli->autocommit(TRUE);
    }

    public function rollback()
    {
        $this->_mysqli->rollback();
        $this->_mysqli->autocommit(TRUE);
    }

    public function getInsertID()
    {
        return $this->_mysqli->insert_id;
    }

    public function affectedRows()
    {
        return $this->_mysqli->affected_rows;
    }

    public function escapeString($string)
    {
        return $this->_mysqli->real_escape_string($string);
    }



    //http://dev.mysql.com/doc/refman/5.5/en/error-messages-server.html
    private function obtainException($errno, $error)
    {
        switch ($errno) {

            case 1048:
                $exception = new DB\Exception\CannotBeNull($error, $errno);
            break;

            case 1054:
                $exception = new DB\Exception\UnknownColumn($error, $errno);
            break;

            case 1062:
                $exception = new DB\Exception\DuplicateKey($error, $errno);
            break;

            case 1064:
                $exception = new DB\Exception\ParseError($error, $errno);
            break;

            case 1146:
                $exception = new DB\Exception\UnknownTable($error, $errno);
            break;

            case 1451:
                $exception = new DB\Exception\ForeignKeyConstraint($error, $errno);
            break;

            case 1452:
                $exception = new DB\Exception\ReferenceNotFound($error, $errno);
            break;

            default:
                $exception = new DB\Exception("Uncaught DB exception: ".$error, $errno);
            break;

        }

        return $exception;
    }


    /* Helper functions */
    public function sanitizeValue($value)
    {
        if (is_string($value)) {
            return "'".$this->escapeString($value)."'";
        } elseif (is_null($value)) {
            return 'NULL';
        } elseif (is_numeric($value)) {
            return $value;
        } elseif (is_array($value)) {

            $sanitizedValues = array();
            foreach($value as $subvalue) {
                $sanitizedValues[] = $this->sanitizeValue($subvalue);
            }
            return '('.implode(', ', $sanitizedValues).')';
        }

        return NULL;
    }

    /**
     * Given a string with parameter names preceded with a colon E.G: "My name is :name"
     * and a hashed array of values E.G array('name' => 'Santiago')
     * replace the parameter name in the string with the sanitized value
     *
     * @param string $string String to parametrize
     * @param Array $parameterValues Array matching the parameter name in the string with the corresponding value
     *
     * @return string The string with sanitized parameters
     */
    public function bindParameters($string, $parameters)
    {
        $parameterNames     = array();
        $sanitizedValues    = array();

        foreach ($parameters as $key => $value) {
            $parameterNames[]   = ":$key";
            $sanitizedValues[]  = $this->sanitizeValue($value);
        }

        return str_replace($parameterNames, $sanitizedValues, $string);
    }


    private function getTableName($incoming)
    {
        if (is_string($incoming)) {
            return $incoming;
        }

        if (get_class($incoming) == 'Phidias\DB\Table') {
            return $incoming->getName();
        }

        trigger_error("invalid table");
    }


    public function select(DB\Select $select)
    {
        return $this->query($select->toSQL());
    }

    public function count(DB\Select $select)
    {
        $resultSet  = $this->query($select->toSQL(TRUE));
        $retval     = $resultSet->fetch_assoc();

        return isset($retval['count']) ? $retval['count'] : NULL;
    }



    /**
     *
     * Sanitize and insert into the specified table
     *
     * $db->insert('people', 1, 'Santiago');       //INSERT INTO people VALUES (1, 'Santiago')
     * $db->insert('people', array('id' => 1, 'name' => 'Santiago'));       //INSERT INTO people (`id`, `name`) VALUES (1, [Santiago])
     * $db->insert('people', array('id', 'name'), array(
     *      array(1, 'Santiago'),
     *      array(2, 'Hugo'),
     *      .....
     * ));       //INSERT INTO people (`id`, `name`) VALUES (1, [Santiago]), (2, 'Hugo) ...
     */

    public function insert($table, $values, $manyValues = NULL)
    {
        $table          = $this->getTableName($table);
        $columnNames    = NULL;
        $targetRecords  = array();

        //insert('people', array('id', 'name'), array( array(1, 'Hugo'), [...] )
        if (is_array($manyValues)) {

            $targetRecords = $manyValues;
            if (is_array($values)) {
                $columnNames = $values;
            }

        //insert('people', array('id' => 1, 'name' => 'Hugo'))
        } elseif (is_array($values)) {

            $columnNames        = array_keys($values);
            $targetRecords[] = $values;

        //insert('people', 1, 'Hugo')
        } else {
            $targetRecord = func_get_args();
            unset($targetRecord[0]);
            $targetRecords[] = $targetRecord;
        }

        if (!count($targetRecords)) {
            return 0;
        }

        $sanitizedRecords = array();
        foreach ($targetRecords as $targetRecord) {

            $fullySanitized = TRUE;
            foreach ($targetRecord as $key => $value) {

                $sanitizedValue = $this->sanitizeValue($value);

                if ($sanitizedValue === NULL) {
                    $fullySanitized = FALSE;
                } else {
                    $targetRecord[$key] = $sanitizedValue;
                }
            }

            if ($fullySanitized) {
                $sanitizedRecords[] = '('.implode(', ', $targetRecord).')';
            }
        }

        if (!count($sanitizedRecords)) {
            throw new DB\Exception\NothingToInsert('no records passed sanitation');
        }

        $query = "INSERT INTO $table";
        if ($columnNames !== NULL) {
            $query .= " (`".implode("`, `", $columnNames)."`) ";
        }
        $query .= " VALUES ";
        $query .= implode(', ', $sanitizedRecords);

        $this->query($query);
        return $this->affectedRows();
    }

    public function update($table, $values, $condition = NULL, $parameters = NULL)
    {
        if (!is_array($values)) {
            return FALSE;
        }

        $table = $this->getTableName($table);

        $valuesArray = array();
        foreach ($values as $columnName => $value) {
            $sanitizedValue = $this->sanitizeValue($value);
            if ($sanitizedValue !== NULL) {
                $valuesArray[] = "`$columnName` = $sanitizedValue";
            }
        }

        if (!count($valuesArray)) {
            return 0;
        }

        $query = "UPDATE $table SET ".implode(', ', $valuesArray);
        if ($condition) {

            if (is_array($parameters)) {
                $condition = $this->bindParameters($condition, $parameters);
            }

            $query .= " WHERE $condition";
        }

        $this->query($query);
        return $this->affectedRows();
    }

    public function delete($table, $condition = NULL, $parameters = NULL)
    {
        $table = $this->getTableName($table);

        $query = "DELETE FROM $table";
        if ($condition !== NULL) {

            if (is_array($parameters)) {
                $condition = $this->bindParameters($condition, $parameters);
            }

            $query .= " WHERE $condition";
        }

        $this->query($query);
        return $this->affectedRows();
    }



    //Table functions

    public function drop($table)
    {
        $table = $this->getTableName($table);
        return $this->query("DROP TABLE IF EXISTS `$table`");
    }

    public function truncate($table)
    {
        $table = $this->getTableName($table);
        return $this->query("TRUNCATE `$table`");
    }

    public function clear($table)
    {
        $table = $this->getTableName($table);
        $retval = $this->query("DELETE FROM `$table`");
        $this->query("ALTER TABLE `$table` AUTO_INCREMENT = 1");
        return $retval;
    }


    public function create(DB\Table $table, $engine = 'InnoDB', $charset = 'utf8', $collation = 'utf8_general_ci')
    {
        $tableName  = $table->getName();
        $columns    = $table->getColumns();

        $query = "CREATE TABLE IF NOT EXISTS `$tableName` ( \n";

        foreach($columns as $columnData) {

            $columnName         = $columnData['name'];
            $columnType         = $columnData['type'].( isset($columnData['length']) ? '('.$columnData['length'].')' : NULL );
            $columnUnsigned     = isset($columnData['unsigned']) && $columnData['unsigned'] ? 'unsigned' : NULL;
            $columnNull         = isset($columnData['acceptNull']) && $columnData['acceptNull'] ? 'NULL' : 'NOT NULL';

            if (array_key_exists('default', $columnData)) {
                $defaultValue = is_null($columnData['default']) ? 'NULL' : $columnData['default'];
                $columnDefault  = "DEFAULT $defaultValue";
            } else {
                $columnDefault  = NULL;
            }

            $columnIncrement    = isset($columnData['autoIncrement']) && $columnData['autoIncrement'] ? "AUTO_INCREMENT" : NULL;

            $query .= "\t`$columnName` $columnType $columnUnsigned $columnNull $columnDefault $columnIncrement, \n";
        }

        $primaryKeyNames = array_keys($table->getPrimaryKeys());
        $query .= "\tPRIMARY KEY (`".implode('`, `', $primaryKeyNames)."`)";

        $foreignKeys        = $table->getForeignkeys();
        $constraintQueries  = array();

        foreach ($foreignKeys as $columnName => $relationData) {
            $query .= ",\n\tKEY `$columnName` (`$columnName`)";

            $onDelete = isset($relationData['onDelete']) ? "ON DELETE ".$relationData['onDelete'] : NULL;
            $onUpdate = isset($relationData['onUpdate']) ? "ON UPDATE ".$relationData['onUpdate'] : NULL;
            $constraintQueries[] = "ALTER TABLE `$tableName` ADD FOREIGN KEY ( `$columnName` ) REFERENCES `{$relationData['table']}` (`{$relationData['column']['name']}`) $onDelete $onUpdate;";
        }

        $query .= "\n) ENGINE=$engine CHARACTER SET $charset COLLATE $collation;";

        $this->query($query);
        foreach ( $constraintQueries as $constraintQuery ) {
            $this->query($constraintQuery);
        }

        $indexes = $table->getIndexes();
        foreach ($indexes as $name => $columns) {
            $this->query("ALTER TABLE `$tableName` ADD INDEX `$name` (`".implode("`, `", $columns)."`);");
        }
    }

}