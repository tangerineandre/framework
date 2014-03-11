<?php
namespace Phidias\HTTP;

class Request
{
    public static function data($variable = NULL, $defaultValue = NULL)
    {
        $inputRAW  = file_get_contents('php://input');
        $inputJSON = json_decode($inputRAW);
        $inputPOST = isset($_POST) && !empty($_POST) ? $_POST : NULL;

        if ($variable === NULL) {
            return $inputJSON !== NULL ? $inputJSON : ($inputPOST !== NULL ? $inputPOST : $inputRAW);
        }

        return isset($inputJSON->$variable) ? $inputJSON->$variable : (isset($inputPOST[$variable]) ? $inputPOST[$variable] : $defaultValue);
    }

    public static function GET($name = FALSE, $onEmpty = NULL)
    {
        if (!$name) {
            return $_GET;
        }

        return isset($_GET[$name]) && !empty($_GET[$name]) ? $_GET[$name] : $onEmpty;
    }

    public static function POST($name = FALSE, $onEmpty = NULL)
    {
        if (!$name) {
            return $_POST;
        }

        return isset($_POST[$name]) && !empty($_POST[$name]) ? $_POST[$name] : $onEmpty;
    }

    public static function JSON($name = NULL, $onEmpty = NULL, $asArray = FALSE)
    {
        $incomingJSON = json_decode(file_get_contents('php://input'), $asArray);
        if (!$incomingJSON) {
            return NULL;
        }

        if ($name === NULL) {
            return $incomingJSON;
        }

        if ($asArray) {
            return isset($incomingJSON[$name]) ? $incomingJSON[$name] : $onEmpty;
        } else {
            return isset($incomingJSON->$name) ? $incomingJSON->$name : $onEmpty;
        }
    }

    /* Return the full path to the uploaded file (via multipart/form-data) */
    public static function file($index)
    {
        return isset($_FILES[$index]) ? $_FILES[$index] : NULL;
    }

    /* Return the value for the specified header */
    public static function header($name = NULL)
    {
        $headers = getallheaders();
        return isset($headers[$name]) ? $headers[$name] : NULL;
    }

    /* Return the current request method */
    public static function method()
    {
        $method = filter_input(INPUT_SERVER, 'REQUEST_METHOD');
        return $method !== NULL ? strtolower($method) : 'get';
    }

    /* Valid methods */
    public static function getValidMethods()
    {
        return array('options', 'get', 'head', 'post', 'put', 'delete', 'trace', 'connect');
    }

    /* Sanitize the given input method */
    public static function sanitizeMethod($requestMethod)
    {
        //Sanitize Request method
        $requestMethod = strtolower(trim($requestMethod));
        if (!in_array($requestMethod, self::getValidMethods())) {
            return NULL;
        }

        return $requestMethod;
    }

    /* As suggested in http://stackoverflow.com/questions/1049401/how-to-select-content-type-from-http-accept-header-in-php */
    public static function getBestSupportedMimeType($mimeTypes = null)
    {
        if (!isset($_SERVER['HTTP_ACCEPT'])) {
            return array('text/plain' => 1);
        }

        // Values will be stored in this array
        $AcceptTypes = Array ();

        // Accept header is case insensitive, and whitespace isn’t important
        $accept = strtolower(str_replace(' ', '', $_SERVER['HTTP_ACCEPT']));
        // divide it into parts in the place of a ","
        $accept = explode(',', $accept);
        foreach ($accept as $a) {
            // the default quality is 1.
            $q = 1;
            // check if there is a different quality
            if (strpos($a, ';q=')) {
                // divide "mime/type;q=X" into two parts: "mime/type" i "X"
                list($a, $q) = explode(';q=', $a);
            }
            // mime-type $a is accepted with the quality $q
            // WARNING: $q == 0 means, that mime-type isn’t supported!
            $AcceptTypes[$a] = $q;
        }
        //arsort($AcceptTypes);

        // if no parameter was passed, just return parsed data
        if (!$mimeTypes) return $AcceptTypes;

        $mimeTypes = array_map('strtolower', (array)$mimeTypes);

        // let’s check our supported types:
        foreach ($AcceptTypes as $mime => $q) {
        if ($q && in_array($mime, $mimeTypes)) return $mime;
        }
        // no mime-type found
        return null;
    }
}