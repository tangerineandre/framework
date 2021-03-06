<?php
namespace Phidias;

use Phidias\Component\ExceptionHandler;
use Phidias\Component\Configuration;
use Phidias\Component\Language;

/**
  * Phidias Environment
  *
  * This class lets you describe an environment.  That is,
  * * include modules
  * * declare components
  * * run the application
  *
  * @author  Santiago Cortes <santiago.cortes@phidias.com.co>
  *
  */
class Environment
{
    private static $modules          = array();
    private static $components       = array();
    
    /* Module directory structure.  All paths are relative to the module root */
    const DIR_LIBRARIES              = 'libraries';
    const DIR_CONFIGURATION          = 'configuration';
    const DIR_VIEWS                  = 'views';
    const DIR_LAYOUTS                = 'layouts';
    const DIR_TEMP                   = 'temp';

    private static $mainPublicURL    = NULL;
    private static $modulePublicURLs = array();


    /**
      * Declare a component
      *
      * @author  Santiago Cortes <santiago.cortes@phidias.com.co>
      *
      */
    public static function component($componentName, $componentClass)
    {
        self::$components[$componentName] = $componentClass;
    }


    /**
      * Include a module
      *
      * @param string $modulePath Root folder of the included module
      * @param string $publicURL optional URL in which the module's public directory is published
      *
      * @return int Returns the number of elements.      
      *
      */
    public static function module($modulePath, $publicURL = NULL)
    {
        $realPath = realpath($modulePath);

        if (!$realPath) {
            trigger_error("module '$modulePath' not found", E_USER_ERROR);
        } else {
            self::$modules[]                    = $realPath;
            self::$modulePublicURLs[$realPath]  = $publicURL;
        }
    }

    /**
      * Include a module
      *
      * @param string $url The URL where this module's public folder is available
      *
      */
    public static function URL($url)
    {
        self::$mainPublicURL = $url;
    }

    /**
      * Execute the application
      *
      */
    public static function start()
    {
        if (isset($_GET['__debug'])) {
            Debug::enable();
        }

        self::initialize();

        try {

            $resourceURI        = null;
            $resourceAttributes = array();
            $requestData        = null;
            $requestMethod      = 'get';

            //CLI
            if (php_sapi_name() == 'cli') {

                $arguments = $_SERVER['argv'];

                if (count($arguments) < 3) {
                    self::usage();
                }

                $resourceURI        = $arguments[2];
                $requestMethod      = strtolower(trim($arguments[1]));
                $resourceAttributes = array();
                if (isset($arguments[3])) {
                    parse_str($arguments[3], $resourceAttributes);
                }

            } else {

                $resourceURI        = HTTP\Request::GET('_url');
                $resourceAttributes = HTTP\Request::GET();
                $requestMethod      = HTTP\Request::method();
                $requestData        = HTTP\Request::data();
                unset($resourceAttributes['_url']);

            }


            /* execute the resource */
            $resource = new Resource($resourceURI, $resourceAttributes);
            $resource->accept(HTTP\Request::getBestSupportedMimeType());

            $response = $resource->execute($requestMethod, $requestData);

            HTTP\Response::code($response->code, $response->message);
            HTTP\Response::contentType($response->contentType);
            HTTP\Response::headers($response->headers->all());

            echo $response->body;

        } catch (\Exception $e) {
            echo ExceptionHandler::handle($e);
        }

        self::finalize();
    }

    private static function usage()
    {
        echo "Usage: \n";
        echo "index.php [method] [resource]\n\n\n";
        exit;
    }

    private static function initialize()
    {
        Debug::startBlock('initializing environment');

        /* Add the framework to the bottom of the stack */
        array_unshift(self::$modules, realpath(__DIR__.'/../../'));


        /* Determine the main public URL */
        if (self::$mainPublicURL === NULL && isset($_SERVER['HTTP_HOST'])) {
            self::$mainPublicURL = "http" . (isset($_SERVER["HTTPS"]) ? "s://" : "://") . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
        }


        /* Add invoking application to the top of the stack.
         * Environment::start() is being run from: [CURRENT APPLICATION]/public/index.php
         * so the path to the current application (main module) is the current previous folder
        */
        self::module('../');


        /* Register environment autoloader */
        spl_autoload_register('self::autoload');


        /* Add all module paths to the include path (priorizing the top of the stack) */
        $pathAdditions  = array();
        for ($c = count(self::$modules)-1; $c >= 0; $c--) {
            $pathAdditions[] = self::$modules[$c].DIRECTORY_SEPARATOR.self::DIR_LIBRARIES;
        }
        $path = get_include_path().PATH_SEPARATOR.implode(PATH_SEPARATOR, $pathAdditions);
        set_include_path($path);
        Debug::add('include path: '.$path);


        /* Set class aliases for configured components */
        foreach (self::$components as $componentName => $componentClass) {

            if (!class_exists($componentClass)) {
                trigger_error("component class '$componentClass' not found", E_USER_ERROR);
            }

            $componentReflection = new \ReflectionClass($componentClass);
            if (!$componentReflection->implementsInterface("Phidias\Component\\{$componentName}Interface")) {
                trigger_error("component class '$componentClass' does not implement interface 'Phidias\Component\\$componentName\Interface'", E_USER_ERROR);
            }

            class_alias($componentClass, "Phidias\Component\\".$componentName);
        }


        /* Load configuration */
        Configuration::load();

        /* Set PHP INI variables from configuration */
        $iniVariables = Configuration::getAll("php.");
        foreach ($iniVariables as $iniKey => $iniValue) {
            ini_set($iniKey, $iniValue);
        }

        /* Include dictionaries */
        if ($languageCode = Configuration::get('phidias.environment.language')) {
            Debug::startBlock("loading language '$languageCode'");
            foreach (self::$modules as $context) {
                Language::load($languageCode, $context);
            }
            Debug::endBlock();
        }

        /* Include all files in folders configured via environment.initialize.* */
        $initializationFolders = Configuration::getAll('phidias.environment.initialize.');
        foreach ($initializationFolders as $folder) {
            $initializationFiles = self::listDirectory($folder, TRUE, FALSE);
            foreach ($initializationFiles as $initializationFile) {
                Debug::startBlock("including initialization file '$initializationFile'", 'include');
                include $initializationFile;
                Debug::endBlock();
            }
        }

        Debug::endBlock();
    }

    private static function finalize()
    {
        Debug::startBlock("finalizing environment");

        /* Include all files in folders configured via environment.finalize.* */
        $finalizationFolders = Configuration::getAll('phidias.environment.finalize.');
        foreach ($finalizationFolders as $folder) {
            $finalizationFiles = self::listDirectory($folder, TRUE, FALSE);
            foreach ($finalizationFiles as $finalizationFile) {
                Debug::startBlock("including finalization file '$finalizationFile'", 'include');
                include $finalizationFile;
                Debug::endBlock();
            }
        }

        Debug::endBlock();

        /* Flush debug data */
        Debug::flush();
    }


    /* The following functions handle finding and listing files from the current module stack */
    public static function realPath($folder)
    {
        return realpath('../')."/$folder";
    }


    public static function autoload($class)
    {
        $classBaseName = str_replace( array('\\', '_'), '/', $class );

        /* Straight correspondance to modules */
        if ($filename = self::findFile(self::DIR_LIBRARIES."/$classBaseName.php")) {
            Debug::startBlock("autoloading '$class' from '$filename'", 'include');
            include_once $filename;
            Debug::endBlock();
            return;
        }
    }

    /* Given a filename reltive to the module root, find the file from the top of the stack
     * Returns the full path to the found file, NULL otherwise
     */
    public static function findFile($filename)
    {
        for ($c = count(self::$modules)-1; $c >= 0; $c--) {
            $currentModule = self::$modules[$c];

            $targetFile = "$currentModule/$filename";

            if (is_file($targetFile)) {
                return $targetFile;
            }
        }

        return NULL;
    }

    /* Given a filename reltive to the module root, find the file from the top of the stack
     * Returns the full path to the found file, NULL otherwise
     */
    public static function findFolder($folder)
    {
        for ($c = count(self::$modules)-1; $c >= 0; $c--) {
            $currentModule = self::$modules[$c];
            if (is_dir("$currentModule/$folder")) {
                return "$currentModule/$folder";
            }
        }

        return NULL;
    }


    public static function glob($pattern, $flags = 0)
    {
        $retval = array();
        foreach (self::$modules as $currentModule) {
            $retval = array_merge($retval, glob($currentModule.DIRECTORY_SEPARATOR.$pattern, $flags));
        }

        return $retval;
    }

    /* List full paths to all files and/or directories contained within every module inside the relative folder $directory
     * from the bottom of the stack */
    public static function listDirectory($directory, $showFiles = TRUE, $showDirectories = TRUE)
    {
        $retval = array();

        foreach (self::$modules as $modulePath) {
            $tmp = Filesystem::listDirectory($modulePath."/$directory", $showFiles, $showDirectories);
            foreach ($tmp as $basename) {
                $retval[] = $modulePath."/$directory/".$basename;
            }
        }

        return $retval;
    }

    /* Determines the module that contains $filename ($filename must contain a full path) */
    public static function findModule($filename)
    {
        foreach (self::$modules as $modulePath) {
            if (strpos($filename, $modulePath) === 0) {
                return $modulePath;
            }
        }

        return FALSE;
    }

    /* Determines the URL corresponding to the specified module's public directory */
    public static function getPublicURL($module = NULL)
    {
        return $module !== NULL && isset(self::$modulePublicURLs[$module]) ? self::$modulePublicURLs[$module] : self::$mainPublicURL;
    }

    /* Finds all files postfixed with $classname */
    public static function findClasses($classname)
    {
        $retval = array();

        for ($c = count(self::$modules)-1; $c >= 0; $c--) {
            self::findClassesInFolder($classname, self::$modules[$c].'/'.self::DIR_LIBRARIES, $retval);
        }

        return $retval;
    }

    private static function findClassesInFolder($classname, $folder, &$retval, $trail = NULL)
    {
        if ($trail === NULL) {
            $trail = $folder.'/';
        }

        $files = Filesystem::listDirectory($folder);

        foreach ($files as $file) {

            if (is_file($folder.'/'.$file) && basename($file) == $classname.'.php') {

                /* get the declared class */
                $contents = file_get_contents($folder.'/'.$file);

                $matches = array();
                preg_match("/class ([a-zA-Z0-9_]+)/", $contents, $matches);
                if (!isset($matches[1])) {
                    continue;
                }

                $foundClassname = $matches[1];

                /* determine the namespace */
                $matches = array();
                preg_match("/namespace ([a-zA-Z0-9_\\\\]+)/", $contents, $matches);

                if (isset($matches[1])) {
                    $foundClassname = "\\" . $matches[1].'\\'.$foundClassname;
                }

                $retval[] = $foundClassname;

            } else if (is_dir($folder.'/'.$file)) {
                self::findClassesInFolder($classname, $folder.'/'.$file, $retval, $trail);
            }
        }
    }

}