<?php
namespace Phidias\Core;

use \Phidias\Component\Configuration;
use \Phidias\Component\Language;
use \Phidias\Component\View;
use \Phidias\Component\Authorization;

class Application
{
    private static $depth  = -1;
    private static $stack  = array();
    private static $contentTypes = array();

    public static function setContentTypes($contentTypes)
    {
        self::$contentTypes = $contentTypes;
    }

    public static function getContentTypes()
    {
        return self::$contentTypes;
    }

    public static function getDepth()
    {
        return self::$depth;
    }

    public static function getResource($depth = 0)
    {
        return isset(self::$stack[$depth][0]) ? self::$stack[$depth][0] : NULL;
    }

    public static function currentResource()
    {
        return self::getResource(0);
    }

    /* Given a request method, URI and attributes (i.e. a request) 
        and an array of accepted mimetypes, this method will 
        find the resource, authorize it, execute it and optionally wrap it
        in a layout.
    */
    public static function run($requestMethod, $URI = NULL, $attributes = NULL)
    {
        if ($URI === NULL) {
            $URI = Configuration::get('phidias.application.defaults.resource');
        }

        $resource = new Resource($URI, $attributes);

        /* Increase depth */
        self::$depth++;
        if (self::$depth > Configuration::get('phidias.application.maxDepth')) {
            Debug::add("reached max depth");
            return;
        }

        /* Authorize resource */
        Debug::startBlock("authorizing '$URI'");
        if (!Authorization::authorized($requestMethod, $URI)) {
            throw new Application\Exception\Unauthorized(array('resource' => $URI));
        }
        Debug::endBlock();

        /* TODO: implement resource output cache */

        /* Run resource and capture output */
        Debug::startBlock("running $requestMethod $URI");
        $output = $resource->run($requestMethod);
        Debug::endBlock();

        /* Set corresponding HTTP response Content-Type */
        if ($contentType = $resource->getContentType()) {
            HTTP\Response::header('Content-Type', $contentType);
        }

        /* TODO: Wrap in layout, if applies */

        self::$depth--;

        return $output;
    }

}