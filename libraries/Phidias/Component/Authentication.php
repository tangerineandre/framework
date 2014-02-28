<?php
namespace Phidias\Component;

class Authentication extends Persistent implements AuthenticationInterface
{
    protected $credentials = array();

    public static function setCredential($credentialName, $value)
    {
        $persistentObject = self::singleton();

        $persistentObject->credentials[$credentialName] = $value;
    }

    public static function getCredential($credentialName)
    {
        $persistentObject = self::singleton();

        return isset($persistentObject->$credentials[$credentialName]) ? $persistentObject->$credentials[$credentialName] : NULL;
    }

    public static function getCredentials()
    {
        $persistentObject = self::singleton();

        return $persistentObject->credentials;
    }

    public static function clear()
    {
        $persistentObject = self::singleton();
        $persistentObject->credentials = array();

    	$persistentObject->forget();
    }
}