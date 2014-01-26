<?php
namespace Phidias\Component;

use Phidias\Core\Route;
use Phidias\Core\Debug;

class ExceptionHandler implements ExceptionHandlerInterface
{
    public static function handle(\Exception $e)
    {
        Debug::collapseAll();

        Debug::add($e->getMessage(), 'error');

        $output = NULL;

        $exceptionView = str_replace(array('_','\\'), '/', strtolower( str_replace('_Exception', '', get_class($e)) ));
        $viewData      = Route::view($exceptionView);
        
        if ($viewData === NULL) {
            $exceptionView = "exceptions/default";
            $viewData      = Route::view($exceptionView);
        }

        /* No view for this exception found anywhere */
        if ($viewData === NULL) {
            $output = dump($e, TRUE);
        } else {
            $templateFile   = $viewData['template'];
            $viewClass      = $viewData['class'];
            $viewObject     = new $viewClass($viewData['url']);
            $viewObject->assign('exception', $e);

            $output = $viewObject->fetch($templateFile);
        }

        return $output;
    }
}