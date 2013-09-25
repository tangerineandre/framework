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

    public static function escapeString($string)
    {
        return addcslashes($string, "'");
    }


    public function __construct($mysqli)
    {
        $this->_mysqli = $mysqli;
    }

    public function query($query)
    {
        Debug::startBlock(strlen($query) > 1024 ? '[Query too long to debug]' : $query, 'SQL');
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
                $exception = new DB\Exception($error, $errno);
            break;

        }

        return $exception;
    }

}