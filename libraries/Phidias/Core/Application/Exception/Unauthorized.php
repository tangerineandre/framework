<?php
namespace Phidias\Core\Application\Exception;

use Phidias\Core\HTTP\Response;

class Unauthorized extends \Exception {

    public function __construct($message = NULL, $code = NULL, $previous = NULL) {
        Response::code(401);
        parent::__construct($message, $code, $previous);
    }

}