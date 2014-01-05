<?php
namespace Phidias\Core\HTTP;

class Request
{
    public static function GET($name = FALSE, $onEmpty = NULL)
    {
        if ( !$name ) {
            return $_GET;
        }

        return isset($_GET[$name]) && !empty($_GET[$name]) ? $_GET[$name] : $onEmpty;
    }

    public static function POST($name = FALSE, $onEmpty = NULL)
    {
        if ( !$name ) {
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

    public static function header($name = NULL)
    {
        $headers = getallheaders();
        return isset($headers[$name]) ? $headers[$name] : NULL;
    }

    /* As suggested in http://stackoverflow.com/questions/1049401/how-to-select-content-type-from-http-accept-header-in-php */
    public static function getBestSupportedMimeType($mimeTypes = null)
    {
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
        arsort($AcceptTypes);

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