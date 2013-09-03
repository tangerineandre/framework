<?php
namespace Phidias\Component;

use Phidias\Core\Debug;
use Phidias\Core\Configuration;
use Phidias\Core\Route;
use Phidias\Component\HTTP\Request;
use Phidias\Component\HTTP\Response;

class Navigation
{
    private static $_trail;

    public static function link($resource = NULL, $attributes = array())
    {
        $trail = self::$_trail === NULL ? Request::GET('_trl') : self::encodeTrail(self::$_trail);
        if ($resource && $trail) {
            $attributes['_trl'] = $trail;
        }

        /* Preserve debugger settings */
        if (Debug::isEnabled()) {
            $attributes['__debug'] = 1;
        }

        return rtrim(Configuration::get('application.URL'),'/').'/'.$resource.(count($attributes) ? '?'.http_build_query($attributes) : NULL);
    }

    public static function redirect($resource = NULL, $attributes = NULL)
    {
        Response::header('Location', self::link($resource, $attributes));
    }


    public static function setNode($node)
    {
        if ( self::$_trail !== NULL ) {
            return;
        }

        self::$_trail = self::decodeTrail(Request::GET('_trl'));

        $node->setURL(self::getCurrentURL());
        self::$_trail[] = $node;
    }

    public static function decodeTrail($trailString)
    {
        $shared = new Shared('navigation.nodes');
        return $shared->get($trailString, array());
    }

    public static function encodeTrail($trail)
    {
        $key    = md5(serialize($trail));

        $shared = new Shared('navigation.nodes');
        $shared->set($key, $trail);
        return $key;
    }

    public static function getTrail()
    {
        return self::$_trail === NULL ? self::decodeTrail(Request::GET('_trl')) : self::$_trail;
    }

    public static function getCurrentURL()
    {
        return 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
    }


    public static function getNode($resource)
    {
        if ( !$route = Route::controller($resource, 'nav') ) {
            return;
        }

        list($resource, $class, $method, $arguments) = $route;

        if (substr($method, -3) !== 'nav') {
            return NULL;
        }
        
        $controllerObject = new $class;
        return call_user_func_array( array($controllerObject, $method), $arguments );
    }
}