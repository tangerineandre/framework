<?php
return array(

    /* Initialization folders 
     * (there can be as many environment.initialize.* directives as needed)  */
    'environment.initialize.general' => 'initialize',

    /* Application parameters */
    'application.max_depth' => 5,

    /* Default controller */
    'controller.default'    => 'default',

    /* PHP.ini directives */
    'php.error_reporting'   => 0,
    'php.display_errors'    => 0,

    /* View component configuration */
    'view.format'           => 'html',
    'view.extension'        => 'phtml',

    /* Use language as prefix to template path */
    'route.template.prefixLanguage' => TRUE,
    'route.layout.prefixLanguage'   => TRUE
);