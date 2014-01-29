<?php
/*  

A VIEW is the representation of any data
layed out in a template
and rendered as a specific content type.

You may provide a view with available templates
and accepted content types as an array with the type in the key and the quality as the value (same format as the "Accept" HTTP header)


//Initialize a view
$v = new View;

//Add some data
$v->set('title', 'A title');
$v->set('conrtents', array(....));

//Set template (or list of possible templates)
$v->addTemplate($template)

$v->acceptTypes($types);

$v->render();


The view will render the output


*/
namespace Phidias;

use Phidias\Component\Language;
use Phidias\Component\Configuration;

class View
{
	private $data;
	private $availableTemplates;
	private $acceptedTypes;
    
    private $outputContentType;


    private static $contentTypeHandlers;

    private static function loadContentTypeHandlers()
    {
        if (self::$contentTypeHandlers !== NULL) {
            return;
        }

        $configuredFormats = Configuration::getObject('phidias.view');

        foreach ($configuredFormats as $formatName => $formatData) {
            if (!isset($formatData->mimetypes) || !is_array($formatData->mimetypes)) {
                continue;
            }

            foreach ($formatData->mimetypes as $mimeType) {

                if (!isset(self::$contentTypeHandlers[$mimeType])) {
                    self::$contentTypeHandlers[$mimeType] = array();
                }

                $handler = array(
                    'folder'    => $formatData->folder,
                    'extension' => $formatData->extension,
                    'component' => isset($formatData->component) ? $formatData->component : 'Phidias\Component\Template'
                );

                self::$contentTypeHandlers[$mimeType][] = $handler;
            }

        }
    }


    public function __construct($availableTemplates = array(), $acceptedTypes = array())
    {
        $this->data               = array();
        $this->availableTemplates = $availableTemplates;
        $this->acceptedTypes      = $acceptedTypes;

        self::loadContentTypeHandlers();
    }

    public function getContentType()
    {
        return $this->outputContentType;
    }

    public function set($variableName, $variableValue)
    {
        $this->data[$variableName] = $variableValue;
    }

    public function templates($templates)
    {
        $this->availableTemplates = (array)$templates;
    }

    public function acceptTypes($types)
    {
        $this->acceptedTypes = (array)$types;
    }


    public function render()
    {
        /* The first priority is to comply with the accepted types */
        foreach ($this->acceptedTypes as $contentType => $quality) {

            /* Loop through each template */
            foreach ($this->availableTemplates as $template) {

                $templateHandler = $this->getTemplateHandler($template, $contentType);

                if ($templateHandler === NULL) {
                    continue;
                }


                $this->outputContentType = $contentType;

                $templateComponentClass = $templateHandler['component'];
                $templateComponent      = new $templateComponentClass($templateHandler['URL']);
                $templateComponent->assign($this->data);

                return $templateComponent->fetch($templateHandler['filename']);                
            }

        }
    }


    private function getTemplateHandler($template, $contentType)
    {
        if (!isset(self::$contentTypeHandlers[$contentType])) {
            return NULL;
        }

        foreach (self::$contentTypeHandlers[$contentType] as $handler) {

            $folder    = $handler['folder'];
            $extension = $handler['extension'];
            $component = $handler['component'];


            /* First, look for a language specific file */
            if ($languageCode = Language::getCode()) {

                $targetFile = "$folder/$languageCode/$template.$extension";
                $foundFile  = Environment::findFile($targetFile);

                if ($foundFile) {
                    return array(
                        'component' => $component,
                        'filename'  => $foundFile,
                        'URL'       => Environment::getPublicURL(Environment::findModule($foundFile))
                    );
                }
            }

            /* Then look for specified template */
            $targetFile = "$folder/$template.$extension";
            $foundFile  = Environment::findFile($targetFile);

            if ($foundFile) {
                return array(
                    'component' => $component,
                    'filename'  => $foundFile,
                    'URL'       => Environment::getPublicURL(Environment::findModule($foundFile))
                );
            }           

        }

        return NULL;
    }

}