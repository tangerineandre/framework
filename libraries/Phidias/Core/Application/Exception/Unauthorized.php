<?php
namespace Phidias\Core\Application\Exception;

class Unauthorized extends \Exception {

    public function __construct($message = NULL, $code = NULL, $previous = NULL) {
        \Phidias\Component\HTTP\Response::code(401);
        parent::__construct($message, $code, $previous);
    }

}