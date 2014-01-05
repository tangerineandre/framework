<?php
namespace Phidias\Core\Application\Exception;

class ResourceNotFound extends \Exception {

    public function __construct($message = NULL, $code = NULL, $previous = NULL) {
        \Phidias\Core\HTTP\Response::code(404);
        parent::__construct($message, $code, $previous);
    }

}