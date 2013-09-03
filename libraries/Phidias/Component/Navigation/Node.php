<?php
namespace Phidias\Component;

class Navigation_Node
{
    private $_url;

    public function getFace()
    {
        return 'Im da face';
    }

    public function getURL()
    {
        return $this->_url;
    }

    public function setURL($url)
    {
        $this->_url = $url;
    }

    public function getMenu()
    {

    }
}
