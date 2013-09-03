<?php
namespace Phidias\ORM;

use Phidias\Core\Configuration;
use Phidias\Core\Debug;

/* A MySQLi Singleton / Alias */
class DB
{
    private static $_instances;

    private $_mysqli;

    public static function connect($identifier = NULL)
    {
        if ( $identifier !== NULL ) {
            $identifier = '.'.$identifier;
        }

        if (self::$_instances === NULL) {
            self::$_instances = array();
        }

        if (isset(self::$_instances[$identifier])) {
            return self::$_instances[$identifier];
        }

        Debug::startBlock("connecting to DB$identifier", 'SQL');
        $mysqli = new \mysqli(
            Configuration::get("orm.db$identifier.host"),
            Configuration::get("orm.db$identifier.username"),
            Configuration::get("orm.db$identifier.password"),
            Configuration::get("orm.db$identifier.database")
        );

        if ($mysqli->connect_errno) {
            throw new DB\Exception\Connect($mysqli->connect_error);
        }

        if ($charset = Configuration::get("orm.db$identifier.charset")) {
            $mysqli->set_charset($charset);
        }
        Debug::endBlock();

        self::$_instances[$identifier] = new DB($mysqli);
        return self::$_instances[$identifier];
    }

    public function __construct($mysqli)
    {
        $this->_mysqli = $mysqli;
    }

    public static function escapeString($string)
    {
        return addcslashes($string, "'");
    }

    public function query($query)
    {
        $debugMessage   = strlen($query) > 1024 ? 'LONG QUERY' : $query;
        $callbackOffset = 8;
        $callbacks      = array(
            array('Phidias\ORM\Dataset\Iterator', 'rewind'),
            array('Phidias\ORM\Entity', 'find'),
            array('Phidias\ORM\Chainable', 'update'),
            array('Phidias\ORM\Chainable', 'delete')
        );

        Debug::startBlock($debugMessage, 'SQL', $callbacks);
        if ( !$retval = $this->_mysqli->query($query) ) {
            throw new DB\Exception\Query($this->_mysqli->error);
        }
        Debug::endBlock();

        return $retval;
    }

    public function getInsertID()
    {
        return $this->_mysqli->insert_id;
    }

    public function affectedRows()
    {
        return $this->_mysqli->affected_rows;
    }

}