<?php
return array(
    
    /* Initialization folders 
    * (there can be as many environment.initialize.* directives as needed)  */
    'environment.initialize.general' => 'initialize',
    
    /* Finalization folders 
    * (there can be as many environment.finalize.* directives as needed)  */
    'environment.finalize.general'   => 'finalize',
    
    'environment.language'           => 'es',     
    
    /* Application parameters */
    'application.max_depth'          => 5,
    
    /* Default resource */
    'resource.default'             => 'default',



    /* Configuration of different view formats
    New formats can be declared as view.format.*.
    specifying mimetypes, folder, extension and (optionally) component (i.e. myComponents\View\Smarty) which defaults to \Phidias\Component\View
    */
    'view.format.html.mimetypes'     => array('text/html', 'application/xhtml+xml', 'application/xml'),
    'view.format.html.folder'        => 'views/html', //folder relative to the root of the module
    'view.format.html.extension'     => 'phtml', //folder relative to the root of the module
    
    'view.format.json.mimetypes'     => array('text/plain', 'application/json'),
    'view.format.json.folder'        => 'views/json', //folder relative to the root of the module
    'view.format.json.extension'     => 'json', //folder relative to the root of the module

);