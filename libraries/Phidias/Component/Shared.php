<?php
namespace Phidias\Component;

use Phidias\Core\Environment;
use Phidias\Core\Filesystem;

class Shared implements Shared_Interface
{
    private $_name;
    private $_dir;

    public function __construct($shareName)
    {
        $this->_name = $shareName;
        $this->_dir = Environment::DIR_TEMP.'/'.$this->_name;

        if ( !is_dir($this->_dir) ) {
            mkdir($this->_dir, 0777, TRUE);
        }
    }

    public function get($key, $defaultValue = NULL)
    {
        $fileName = $this->_getFilename($key);
        return is_file($fileName) ? unserialize( file_get_contents($fileName) ) : $defaultValue;
    }

    public function set($key, $value)
    {
        if ( $value === NULL ) {
            return $this->delete($key);
        }

        $fileName = $this->_getFilename($key);
        return file_put_contents($fileName, serialize($value));
    }

    public function delete($key)
    {
        return unlink( $this->_getFilename($key) );
    }

    public function destroy()
    {
        Filesystem::rmdir($this->_dir);
    }

    private function _getFilename($key)
    {
        return $this->_dir.'/'.md5($key);
    }
}