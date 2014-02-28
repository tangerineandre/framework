<?php
namespace Phidias\Component;

interface AuthenticationInterface
{
	public static function setCredential($credentialName, $value);
	public static function getCredential($credentialName);
	public static function getCredentials();
	public static function clear();
}