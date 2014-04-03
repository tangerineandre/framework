<?php
return array(

    /* 
    Configuration of different view formats:
    View format directives are as phidias.view.[FORMAT NAME].[ATTRIBUTE]
    Attributes are:  mimetypes, folder, extension and (optionally) component (i.e. myComponents\View\Smarty) which defaults to \Phidias\Component\View
    */

    'phidias.view.html.mimetypes'     => array('text/html', 'application/xhtml+xml', 'application/xml'),
    'phidias.view.html.folder'        => 'views/html', //folder relative to the root of the module
    'phidias.view.html.extension'     => 'phtml', //folder relative to the root of the module

    'phidias.view.json.mimetypes'     => array('application/json'),
    'phidias.view.json.folder'        => 'views/json', //folder relative to the root of the module
    'phidias.view.json.extension'     => 'json', //folder relative to the root of the module

    'phidias.view.txt.mimetypes'     => array('text/plain'),
    'phidias.view.txt.folder'        => 'views/txt',
    'phidias.view.txt.extension'     => 'txt',

);