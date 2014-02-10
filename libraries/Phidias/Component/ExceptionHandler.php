<?php
namespace Phidias\Component;

use Phidias\Debug;
use Phidias\View;
use Phidias\HTTP;

class ExceptionHandler implements ExceptionHandlerInterface
{
    public static function handle(\Exception $exception)
    {
        Debug::collapseAll();
        Debug::add($exception->getMessage(), 'error');

        HTTP\Response::code($exception->getCode(), $exception->getMessage());

        $output = NULL;

        $exceptionTemplate = str_replace(array('_','\\'), '/', strtolower( str_replace('_Exception', '', get_class($exception)) ));

        $view = new View;
        $view->templates(array($exceptionTemplate, "exceptions/default"));
        $view->acceptTypes(HTTP\Request::getBestSupportedMimeType());

        $view->set('exception', $exception);

        $output = $view->render();

        if ($output) {
            HTTP\Response::contentType($view->getContentType());
            return $output;
        }

        return dump($exception, TRUE);
    }
}