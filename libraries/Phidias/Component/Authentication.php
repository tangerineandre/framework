<?php
namespace Phidias\Component;

class Authentication extends Persistent
{
    public function login()
    {
    }

    public function logout()
    {
    	$this->clear();
    }
}