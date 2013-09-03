<?php
namespace Phidias\Core;

use Phidias\Component\HTTP\Request;

class Environment
{
    /* Directory structure */
    const DIR_LIBRARIES     = 'libraries';
    const DIR_TEMP          = '../temp';

    const DIR_CONFIGURATION = 'application/configuration';
    const DIR_CONTROLLERS   = 'application/modules';
    const DIR_VIEWS         = 'application/views';
    const DIR_LANGUAGES     = 'application/languages';
    const DIR_CONTROL       = 'application/control';
    const DIR_LAYOUTS       = 'application/layouts';

    private static $_stack;
    private static $_publicDirectoryHashes;

    private function parseStack($dir)
    {
        self::$_stack[] = realpath($dir);

        if ( is_file($dir.'/public/environment.php') ) {
            $dependencies = include $dir.'/public/environment.php';
            foreach ($dependencies as $node) {
                self::parseStack($node);
            }
        }
    }

    public static function getPublicURL($node)
    {
        $applicationURL = rtrim(Configuration::get('application.URL'), '/').'/';
        return isset(self::$_publicDirectoryHashes[$node]) ? $applicationURL.self::$_publicDirectoryHashes[$node] : $applicationURL;
    }

    public static function initialize()
    {
        Debug::startBlock('initializing environment');

        spl_autoload_register('self::autoload');

        /* Inclusion hierarchy */
        self::$_stack   = array();
        $trace          = debug_backtrace();
        for ($c=count($trace)-1; $c>=0; $c--) {
            $node = str_replace(DIRECTORY_SEPARATOR.'public', '', dirname($trace[$c]['file']));
            self::parseStack($node);
        }

        /* Determine path additions */
        $pathAdditions  = array();
        foreach (self::$_stack as $node) {
            $pathAdditions[]    = $node.DIRECTORY_SEPARATOR.self::DIR_LIBRARIES;
        }
        $path = get_include_path().PATH_SEPARATOR.implode(PATH_SEPARATOR, $pathAdditions);
        set_include_path($path);
        Debug::add('include path: '.$path);

        /* Include configuration */
        Debug::startBlock('including configuration files');
        $configurations = self::listDirectory(self::DIR_CONFIGURATION, TRUE, FALSE);
        foreach ( $configurations as $file ) {

            /* Ignore configuration files prefixed with "_" */
            if ( substr($file['name'],0,1) == '_') {
                continue;
            }

            Debug::startBlock("loading configuration from '".$file['name']."'", 'include');
            Configuration::load($file['name'], $file['source']);
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

        $layout = Configuration::get('application.layout');
        if ($layout) {
            Application::setLayout($layout);
        }


        /* Set appropiate response format */
        if ( Request::getBestSupportedMimeType('application/json') ) {
            Application::setLayout(FALSE);
            Configuration::set('view.format', 'json');
            Configuration::set('view.extension', 'json');
        }

        /* Configured component aliases */
        $componentAliases = Configuration::getAll('component.');
        foreach ( $componentAliases as $componentClass => $targetClass ) {
            class_alias($targetClass, "Phidias\Component\\".$componentClass);
        }

        if ( $languageCode = Configuration::get('application.language') ) {
            Debug::startBlock("loading language '$languageCode'");
            Language::setCode($languageCode);
            $dictionaries = self::listDirectory(self::DIR_LANGUAGES."/$languageCode", TRUE, FALSE);
            foreach ( $dictionaries as $file ) {
                Debug::startBlock("loading language file '".$file['name']."'", 'include');
                Language::load($file['name'], $file['source']);
                Debug::endBlock();
            }
            Debug::endBlock();
        }


        /* Set up routing */
        $routes = self::listFileOccurrences(self::DIR_CONTROL.'/routes.php');
        foreach ( $routes as $routeFile ) {
            Debug::startBlock("including routing file '$routeFile'", 'include');
            Route::load(include $routeFile);
            Debug::endBlock();
        }


        Debug::endBlock();
    }

    public static function finalize()
    {
        Debug::flush();
    }

    public static function findFile($filename, &$node = NULL)
    {
        foreach ( self::$_stack as $dir ) {
            if ( is_file("$dir/$filename") ) {
                $node = $dir;
                return "$dir/$filename";
            }
        }
        return FALSE;
    }

    public static function listDirectory($directory, $showFiles = TRUE, $showDirectories = TRUE)
    {
        $contents = array();

        for ( $c = count(self::$_stack)-1; $c >= 0; $c-- ) {
            $tmp = Filesystem::listDirectory(self::$_stack[$c]."/$directory", $showFiles, $showDirectories);
            foreach ( $tmp as $file ) {
                $contents[] = array(
                    'name'      => self::$_stack[$c]."/$directory/$file",
                    'source'    => self::$_stack[$c]
                );
            }
        }

        return $contents;
    }

    public static function listFileOccurrences($file, $topToBottom = TRUE)
    {
        $occurrences = array();

        for ( $c = count(self::$_stack)-1; $c >= 0; $c-- ) {
            $node_file = self::$_stack[$c]."/$file";
            if ( is_file($node_file) ) {
                $occurrences[] = $node_file;
            }
        }

        return $topToBottom ? $occurrences : array_reverse($occurrences);
    }

    public static function autoload($class)
    {
        $classBaseName = str_replace( array('\\', '_'), '/', $class );

        /* Straight correspondance to modules */
        if ( $filename = self::findFile(self::DIR_CONTROLLERS."/$classBaseName.php") ) {
            Debug::startBlock("autoloading '$class' from '$filename'", 'include');
            include $filename;
            Debug::endBlock();
            return;
        }
    }

    public static function findSource($file)
    {
        foreach (self::$_stack as $stackDir) {
            if (strpos($file, $stackDir) === 0) {
                return $stackDir;
            }
        }

        return FALSE;
    }

}