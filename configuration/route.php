<?php
return array(

    /* Configuration of different view formats
    New formats can be declared as phidias.route.view.*.
    specifying mimetypes, folder, extension and (optionally) component (i.e. myComponents\View\Smarty) which defaults to \Phidias\Component\View
    */
    'phidias.route.view.html.mimetypes'     => array('text/html', 'application/xhtml+xml', 'application/xml'),
    'phidias.route.view.html.folder'        => 'views/html', //folder relative to the root of the module
    'phidias.route.view.html.extension'     => 'phtml', //folder relative to the root of the module

    'phidias.route.view.json.mimetypes'     => array('application/json'),
    'phidias.route.view.json.folder'        => 'views/json', //folder relative to the root of the module
    'phidias.route.view.json.extension'     => 'json', //folder relative to the root of the module

);