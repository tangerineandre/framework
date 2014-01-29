<?php
namespace Phidias\HTTP;

use Phidias\Exception;

class Post
{
    static public function get($varname, $onNull = null)
    {
        return isset($_POST[$varname]) && $_POST[$varname] !== null ? $_POST[$varname] : $onNull;
    }

    static public function required($varname, $msg = '%param% missing')
    {
        $retval = self::get($varname);
        if ( $retval === NULL ) {
            throw new Exception(array('required' => $varname), $msg);
        }

        return $retval;
    }

    static public function getAll($pPrefix = false)
    {
        if ( !$pPrefix ) {
            return $_POST;
        }

        $retval = array();
        while(key($_POST))
        {
            if( strpos(key($_POST), $pPrefix) === 0 )
            {
                $key            = substr( key($_POST), strlen($pPrefix) );
                $value          = current($_POST);
                $retval[$key]   = $value;
            }
            next($_POST);
        }

        reset($_POST);
        return $retval;
    }
}
