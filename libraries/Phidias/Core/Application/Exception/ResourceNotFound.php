<?php
namespace Phidias\Core\Application\Exception;

use Phidias\Core\HTTP\Response;

class ResourceNotFound extends \Exception {

    public function __construct($message = NULL, $code = NULL, $previous = NULL) {
        Response::code(404);
        parent::__construct($message, $code, $previous);
    }

}