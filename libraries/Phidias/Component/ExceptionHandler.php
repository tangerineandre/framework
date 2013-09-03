<?php
namespace Phidias\Component;

use Phidias\Core\Environment;
use Phidias\Core\Application;
use Phidias\Core\Configuration;
use Phidias\Core\Route;
use Phidias\Core\View;
use Phidias\Core\Layout;
use Phidias\Core\Debug;

class ExceptionHandler implements ExceptionHandler_Interface
{
    public static function handle(\Exception $e)
    {
        Debug::collapseAll();
        Debug::add($e->getMessage(), 'error');

        $output = NULL;

        $view = new View;
        $view->assign('exception', $e);

        $view_format        = Configuration::get('view.format', 'html');
        $view_extension     = Configuration::get('view.extension');
        $exception_template = str_replace(array('_','\\'), '/', strtolower( str_replace('_Exception', '', get_class($e)) ));

        $template_file = Environment::findFile( Environment::DIR_VIEWS."/$view_format/exceptions/$exception_template.$view_extension" );
        if ( $template_file && $view->isValid($template_file) ) {
            $output = $view->fetch($template_file);
        }

        if ($output === NULL) {
            $default_file = Environment::findFile( Environment::DIR_VIEWS."/$view_format/exceptions/default.$view_extension" );
            if ( $default_file && $view->isValid($default_file) ) {
                $output = $view->fetch($default_file);
            }
        }

        /* No view for this exception was found. Dump exception variable */
        if ($output === NULL) {
            $output = dump($e, TRUE);
        }

        return $output;
    }
}