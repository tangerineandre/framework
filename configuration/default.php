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
     
     /* Default controller */
     'controller.default'             => 'default',
     
     /* View component configuration */
     'views.html.mimetypes' => array('text/html', 'application/xhtml+xml', 'application/xml'),
     'views.html.folder'    => 'views/html',//folder relative to the root of the module
     'views.html.extension' => 'phtml', //folder relative to the root of the module

     'views.json.mimetypes' => array('text/plain', 'application/json'),
     'views.json.folder'    => 'views/json',//folder relative to the root of the module
     'views.json.extension' => 'json', //folder relative to the root of the module




     
/*
    [text/html] => 1
    [application/xhtml+xml] => 1
    [application/xml] => 0.9

    ['*'/*] =&gt; 1
    [text/plain] =&gt; 1
    [application/json] =&gt; 1

    environment: {
    
        initialize: {
            general: 'initialize'
        },

        finalize: {
            general: 'finalize'
        },

        route: {
            templates: {
                folder: "views/"    //relative path to the module root where the views are stored
            }
        }

        views: {
            html: {
                mimetypes: [text/html, application/xhtml+xml]
            }
        }
    }





*/



     );