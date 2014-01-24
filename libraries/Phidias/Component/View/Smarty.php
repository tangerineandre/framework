<?php
namespace Phidias\Component;

include 'Smarty-3.1.13/libs/Smarty.class.php';

class View_Smarty implements ViewInterface
{
    private $smarty;

    public function __construct()
    {
        $this->smarty = new Smarty();

        $this->smarty->setTemplateDir('application/views/');
        $this->smarty->setCompileDir('tmp/smarty/templates_c/');
        $this->smarty->setConfigDir('tmp/smarty/configs/');
        $this->smarty->setCacheDir('tmp/smarty/cache/');
    }

    public function assign($variable, $value = NULL)
    {
        $this->smarty->assign($variable, $value);
    }

    public function isValid($template)
    {
        return TRUE;
    }

    public function fetch($template)
    {
        return $this->smarty->fetch($template);
    }
}