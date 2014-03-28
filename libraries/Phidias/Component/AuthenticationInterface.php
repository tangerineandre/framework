<?php
namespace Phidias\Component;

/**
  * Phidias Authentication Component
  *
  * The authentication component is a persistent storage of arbitraty credentials.
  *
  * @author  Santiago Cortes <santiago.cortes@phidias.com.co>
  *
  */
interface AuthenticationInterface
{
	public static function setCredential($credentialName, $value);
	public static function getCredential($credentialName);
	public static function getCredentials();
	public static function clear();
}