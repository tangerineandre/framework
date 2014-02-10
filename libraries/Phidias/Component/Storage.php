<?php
/*

e.g.

Storage (conceptualmente)

/
	/folder
		file.ext
		file2.ext2
		... {inf}


	/folder2

	/... {inf}



Uso:

$storage = new Storage("/');	new Storage($root);


Storage::save("/test.txt", "Hola mundo!");

$files = Storage::list("/");

foreach ($files as $file) {
	dump($file);
}


$foo = new Storage('/');
$foo = new Storage('messages');		// /messages

*/
namespace Phidias\Component;

use Phidias\Filesystem;
use Phidias\Component\Configuration;

class Storage implements StorageInterface
{
	private $root;

	public function __construct($root)
	{
		if ($root === NULL) {
			$root = "/";
		}

		$rootPath = realpath(Configuration::get('phidias.component.storage.root', realpath('../').'/storage'));

		if (!$rootPath) {
			trigger_error("configuration 'phidias.component.storage.root' must contain a valid path", E_USER_ERROR);
		}

		$this->root = $rootPath . '/' . trim($root, '/');
	}

	private function sanitizeTarget($target)
	{
		$target = $this->root.'/'.trim($target, '/');

		$dirname = dirname($target);
		Filesystem::createDirectory($dirname);


		return $target;
	}

	public function getList($source = "/")
	{
		return Filesystem::listDirectory($this->sanitizeTarget($source));
	}

	public function isFile($filename)
	{
		return Filesystem::isFile($this->sanitizeTarget($filename));
	}

	public function isDirectory($directory)
	{
		return Filesystem::isDirectory($this->sanitizeTarget($directory));
	}

	public function getSize($file)
	{
		return Filesystem::getSize($this->sanitizeTarget($file));
	}

	public function filePutContents($filename, $data)
	{
		$filename = $this->sanitizeTarget($filename);

		file_put_contents($filename, $data);
	}

	public function fileGetContents($filename)
	{
		if (!$this->isFile($filename)) {
			return NULL;
		}

		return file_get_contents($this->sanitizeTarget($filename));		
	}

	public function copy($source, $destination)
	{
		return copy($this->sanitizeTarget($source), $this->sanitizeTarget($destination));
	}

	public function move($source, $destination)
	{
		return move($this->sanitizeTarget($source), $this->sanitizeTarget($destination));
	}

	public function delete($target)
	{
		return unlink($this->sanitizeTarget($target));
	}	

	public function upload($localFile, $destination)
	{
		return copy($localFile, $this->sanitizeTarget($destination));
	}

	public function getUrl($filename)
	{
		return NULL;
	}

	public function destroy()
	{
		return Filesystem::deleteDirectory($this->root);
	}


	public function isEmpty($source = "/")
	{
		return count($this->getList($source)) === 0;
	}

}