<?php
namespace Phidias\Component\HTTP;

class Get
{
    static public function get($varname, $onNull = null)
    {
        return isset($_GET[$varname]) && $_GET[$varname] !== null ? $_GET[$varname] : $onNull;
    }

    static public function required($varname, $msg = '%param% missing')
    {
        $retval = self::get($varname);
        if ( $retval === NULL ) {
            throw new Application_Exception($msg);
        }

        return $retval;
    }

    static public function getAll($pPrefix = false)
    {
        if ( !$pPrefix ) {
            return $_GET;
        }

        $retval = array();
        while(key($_GET))
        {
            if( strpos(key($_GET), $pPrefix) === 0 )
            {
                $key            = substr( key($_GET), strlen($pPrefix) );
                $value          = current($_GET);
                $retval[$key]   = $value;
            }
            next($_GET);
        }

        reset($_GET);
        return $retval;
    }
}
