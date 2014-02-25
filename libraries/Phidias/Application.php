<?php
namespace Phidias;

use \Phidias\Component\Configuration;
use \Phidias\Component\Language;
use \Phidias\Component\View;
use \Phidias\Component\Authorization;


class Application
{
    private static $requestStack = array();
    private static $depth        = -1;

    public static function getDepth()
    {
        return self::$depth;
    }

    public static function getResource($depth = 0)
    {
        return isset(self::$requestStack[$depth][1]) ? self::$requestStack[$depth][1] : NULL;
    }

    public static function currentResource()
    {
        return self::getResource(0);
    }

    /* Given a request method, resource and attributes (i.e. a request)  
        this method will find the resource, authorize it, execute it 
        and maybe someday wrap it in a layout.
    */
    public static function run($requestMethod, $requestResource = NULL, $attributes = NULL)
    {
        /* Handle CORS preflight requests */
        if ($requestMethod == 'options') {
            HTTP\Response::header("Access-Control-Allow-Origin", "*");
            HTTP\Response::header("Access-Control-Allow-Headers", "Origin, X-Requested-With, Content-Type, Accept");

            return;
        }


        /* Increase depth */
        self::$depth++;

        if (self::$depth > Configuration::get('phidias.application.maxDepth')) {
            Debug::add("reached max depth");
            return;
        }

        //Sanitize resource
        if ($requestResource === NULL) {
            $requestResource = Configuration::get('phidias.application.defaults.resource');
        }
        $requestResource = rtrim($requestResource, '/');

        //Sanitize attributes
        if (!is_array($attributes)) {
            $attributes = array();
        }

        /* Authorize resource */
        Debug::startBlock("authorizing '$requestResource'");
        if (!Authorization::authorized($requestMethod, $requestResource)) {
            throw new Application\Exception\Unauthorized(array('resource' => $requestResource, 'method' => $requestMethod));
        }
        Debug::endBlock();


        /* Everything checks out OK.  Execute the resource */
        Debug::startBlock("running $requestMethod $requestResource");
        self::$requestStack[self::$depth] = array($requestMethod, $requestResource);

        $resource    = new Resource($requestResource, $attributes, HTTP\Request::getBestSupportedMimeType());
        $output      = $resource->run($requestMethod);
        $contentType = $resource->getContentType();

        Debug::endBlock();

        if (self::$depth === 0 && $contentType) {
            HTTP\Response::contentType($contentType);
        }

        self::$depth--;

        return $output;
    }

}