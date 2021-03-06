<?php
namespace Phidias;

class Filesystem
{
    public static function isFile($filename)
    {
        return is_file($filename);
    }

    public static function isDirectory($filename)
    {
        return is_dir($filename);
    }

    public static function listDirectory($directory, $list_files = TRUE, $list_directories = TRUE)
    {
        if ( !is_dir($directory) ) {
            return array();
        }

        $contents   = scandir($directory);
        unset($contents[0]);    //Ignore "."
        unset($contents[1]);    //Ignore ".."

        if ( $list_files && $list_directories ) {
            return $contents;
        }

        $retval     = array();
        foreach ( $contents as $element ) {
            if ( $list_files && is_file("$directory/$element") ) {
                $retval[] = $element;
            }

            if ( $list_directories && is_dir("$directory/$element") ) {
                $retval[] = $element;
            }
        }
        return $retval;
    }


    public static function createDirectory($dir, $perms = 0777)
    {
        if (self::isDirectory($dir)) {
            return TRUE;
        }

        $arrpath    = explode('/', $dir);
        $intdir     = NULL;

        for($cont=0; $cont < count($arrpath); $cont++) {
            $intdir .= $arrpath[$cont].'/';
            if(!is_dir($intdir)) {
                $oldumask = umask(0);
                if (!mkdir($intdir, $perms)) {
                    return FALSE;
                }
                umask($oldumask);
            }
        }

        chmod($dir, $perms);
        return TRUE;
    }

    public static function getSize($filename)
    {
        return filesize($filename);
    }


    public static function delete($file)
    {
        return self::isFile($file) ? unlink($file) : FALSE;
    }

    public static function deleteDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $handle = opendir($dir);
        while(false !== ($file = readdir($handle))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($dir.'/'.$file)) {
                    self::deleteDirectory($dir.'/'.$file);
                } else {
                    self::delete($dir.'/'.$file);
                }
            }
        }
        closedir($handle);

        return rmdir($dir);
    }

    public static function copyDirectory($src, $dst)
    {
        $dir = opendir($src);
        @mkdir($dst);
        while(false !== ( $file = readdir($dir)) ) {
            if (( $file != '.' ) && ( $file != '..' )) {
                if ( is_dir($src.'/'.$file) ) {
                    self::copyDirectory($src.'/'.$file, $dst.'/'.$file);
                }
                else {
                    copy($src.'/'.$file, $dst.'/'.$file);
                }
            }
        }
        closedir($dir);
    }

    public static function createAlias($sourceDirectory, $targetDirectory)
    {
        Debug::startBlock("aliasing $sourceDirectory as $targetDirectory");
        self::copyDirectory($sourceDirectory, $targetDirectory);
        Debug::endBlock();
    }

    public static function mktree($dir, $perms = 0777)
    {
        return self::createDirectory($dir, $perms);
    }

    public static function rmdir($dir)
    {
        return self::deleteDirectory($dir);
    }

    public static function putContents($filename, $data, $flags = 0)
    {
        if (!is_dir($dirname = dirname($filename))) {
            self::mktree($dirname);
        }

        file_put_contents($filename, $data, $flags);
    }
}