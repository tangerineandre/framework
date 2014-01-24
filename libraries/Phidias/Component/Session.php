<?php
namespace Phidias\Component;

use Phidias\Core\Configuration;


class Session implements SessionInterface
{
    private static $_loaded = FALSE;

    private static function _load()
    {
        if ( self::$_loaded ) {
            return;
        }

        ini_set('session.gc_maxlifetime', Configuration::get('session.lifetime', '18000'));    //5 hours.
        session_cache_limiter('nocache');
        session_start();

        self::$_loaded = TRUE;
    }

    public static function set($varname, $value = NULL)
    {
        self::_load();
        global $_SESSION;
        $_SESSION[$varname] = $value;
    }

    public static function get($varname, $default = NULL)
    {
        self::_load();
        return isset($_SESSION[$varname]) ? $_SESSION[$varname] : $default;
    }

    public static function clear($varname)
    {
        self::_load();
        unset($_SESSION[$varname]);
    }

    public static function getAll()
    {
        self::_load();
        return $_SESSION;
    }

    public static function destroy()
    {
        session_regenerate_id();
        session_destroy();
    }
}