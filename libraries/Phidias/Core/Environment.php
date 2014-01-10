<?php
namespace Phidias\Core;

use Phidias\Core\HTTP\Request;
use Phidias\Component\ExceptionHandler;

class Environment
{
    private static $modules = array();

    /* Module directory structure.  All paths are relative to the module root */
    const DIR_LIBRARIES         = 'libraries';
    const DIR_CONFIGURATION     = 'configuration';
    const DIR_LANGUAGES         = 'languages';
    const DIR_VIEWS             = 'views';
    const DIR_LAYOUTS           = 'layouts';

    private static $mainPublicURL       = NULL;
    private static $modulePublicURLs    = array();

    public static function URL($url)
    {
        self::$mainPublicURL = $url;
    }

    public static function module($modulePath, $publicURL = NULL)
    {
        $realPath = realpath($modulePath);

        if (!$realPath) {
            trigger_error("module '$modulePath' not found");
        } else {
            self::$modules[]                    = $realPath;
            self::$modulePublicURLs[$realPath]  = $publicURL;
        }
    }

    public static function start()
    {
        if (isset($_GET['__debug'])) {
            Debug::enable();
        }

        self::initialize();

        try {

            $resource       = Request::GET('_a', Configuration::get('controller.default'));
            $requestMethod  = Request::method();
            $attributes     = Request::GET();
            unset($attributes['_a']);

            echo Application::run($resource, $requestMethod, $attributes);

        } catch (\Exception $e) {

            echo ExceptionHandler::handle($e);
        }

        self::finalize();
    }


    private static function initialize()
    {
        Debug::startBlock('initializing environment');

        /* Add the framework to the bottom of the stack */
        array_unshift(self::$modules, realpath(__DIR__.'/../../../'));

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


        /* Include every file in the configuration folder.  If the included file returns an array, load it as configuration variables */
        Debug::startBlock('including configuration files');
        $configurationFiles = self::listDirectory(self::DIR_CONFIGURATION, TRUE, FALSE);

        foreach ($configurationFiles as $configurationFile) {

            /* Ignore configuration files prefixed with "_" */
            if (substr(basename($configurationFile), 0, 1) == '_') {
                continue;
            }

            Debug::startBlock("loading configuration from '$configurationFile'", 'include');

            $retval = include $configurationFile;
            if (is_array($retval)) {
                Configuration::set($retval);
            }

            Debug::endBlock();
        }
        Debug::endBlock();



        /* Set environment variables from configuration */
        $errorReporting = Configuration::get('php.error_reporting');
        if ( $errorReporting !== NULL ) {
            error_reporting($errorReporting);
        }

        $displayErrors = Configuration::get('php.display_errors');
        if ( $displayErrors !== NULL ) {
            ini_set('display_errors', $displayErrors);
        }

        $timeLimit = Configuration::get('php.time_limit');
        if ( $timeLimit !== NULL ) {
            set_time_limit($timeLimit);
        }

        $timeZone = Configuration::get('php.timezone');
        if ($timeZone) {
            date_default_timezone_set($timeZone);
        }

        $layout = Configuration::get('environment.layout');
        if ($layout) {
            Application::setLayout($layout);
        }

        /* Set appropiate response format */
        if ( Request::getBestSupportedMimeType(array('application/json', 'application/javascript')) ) {
            Application::setLayout(FALSE);
            Configuration::set('view.format', 'json');
            Configuration::set('view.extension', 'json');
        }

        /* Configured component aliases */
        $componentAliases = Configuration::getAll('component.');
        foreach ($componentAliases as $componentClass => $targetClass) {
            class_alias($targetClass, "Phidias\Component\\".$componentClass);
        }

        /* Include dictionaries */
        if ($languageCode = Configuration::get('environment.language')) {
            Debug::startBlock("loading language '$languageCode'");

            Language::setCode($languageCode);
            $dictionaries = self::listDirectory(self::DIR_LANGUAGES."/$languageCode", TRUE, FALSE);
            foreach ($dictionaries as $dictionaryFile) {
                Debug::startBlock("loading language file '".$configurationFile['name']."'", 'include');

                $words = include $dictionaryFile;
                if (is_array($words)) {
                    $context = substr($dictionaryFile, 0, strpos($dictionaryFile, self::DIR_LANGUAGES."/$languageCode")-1);
                    Language::set($words, $context);
                }

                Debug::endBlock();
            }

            Debug::endBlock();
        }

        /* Include all files in folders configured via environment.initialize.* */
        $initializationFolders = Configuration::getAll('environment.initialize.');
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
        $finalizationFolders = Configuration::getAll('environment.finalize.');
        foreach ($finalizationFolders as $folder) {
            $finalizationFiles = self::listDirectory($folder, TRUE, FALSE);
            foreach ($finalizationFiles as $finalizationFile) {
                Debug::startBlock("including finalization file '$finalizationFile'", 'include');
                include $finalizationFile;
                Debug::endBlock();
            }
        }


        Debug::endBlock();

        Debug::flush();
    }


    /* The following functions handle finding and listing files from the current module stack */


    public static function autoload($class)
    {
        $classBaseName = str_replace( array('\\', '_'), '/', $class );

        /* Straight correspondance to modules */
        if ($filename = self::findFile(self::DIR_LIBRARIES."/$classBaseName.php")) {
            Debug::startBlock("autoloading '$class' from '$filename'", 'include');
            include $filename;
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
            if (is_file("$currentModule/$filename")) {
                return "$currentModule/$filename";
            }
        }

        return NULL;
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
    public static function getPublicURL($module)
    {
        return isset(self::$modulePublicURLs[$module]) ? self::$modulePublicURLs[$module] : self::$mainPublicURL;
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
                $retval[] = str_replace(array('/', '.php'), array('_', ''), str_replace($trail, '', $folder.'/'.$file));
            } else if (is_dir($folder.'/'.$file)) {
                self::findClassesInFolder($classname, $folder.'/'.$file, $retval, $trail);
            }
        }
    }

}