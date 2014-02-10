<?php

namespace Phidias\Component\Storage;

use Phidias\Component\Storage;

use Phidias\Image;
use Phidias\Filesystem;
use Phidias\HTTP\Request;
use Phidias\HTTP\Response;


class Controller extends \Phidias\Resource\Controller
{

	public function get($storageID)
	{
		$retval  = array();

		$storage = new Storage($storageID);
		$items   = $storage->getList();

		foreach ($items as $item) {
			if (substr($item, 0, 1) === ".") {
				continue;
			}

			$file = array(
				"name"      => $item,
				"size"      => $storage->getSize($item),
				"thumbnail" => $storage->isFile("/.thumbnails/$item") ? "/.thumbnails/$item" : NULL
			);

			$retval[] = $file;
		}

		return $retval;
	}

	/* Add a new file */
	public function post($storageID)
	{
		$file = Request::file("file");
		if ($file === NULL) {
			return FALSE;
		}

		$storage = new Storage($storageID);
		$storage->upload($file['tmp_name'], $file['name']);

		/* Attempt to create a thumbnail */
		if (strpos($file['type'], 'image') !== FALSE) {
			$type     = str_replace('image/', '', $file['type']);
			$tempFile = Image::createThumbnail($file['tmp_name'], 180, 140, $type);

			if ($tempFile !== NULL) {
				$storage->upload($tempFile, '/.thumbnails/'.$file['name']);
				Filesystem::delete($tempFile);
			}
		}

		return TRUE;
	}

	/* Get the file contents */
	public function getFile($storageID, $filename)
	{
		$storage = new Storage($storageID);

		if ($url = $storage->getUrl($filename)) {
			Response::header('Location', $url);
			return;
		}
		
		if (!$storage->isFile($filename)) {
			throw new \Phidias\Resource\Exception\NotFound(array("resource" => $filename), "file '$filename' not found");
		}

		Response::header("Content-type",		"application");
		Response::header("Content-Disposition",	"attachment; filename=".basename($filename));
		Response::header("Cache-Control",		"must-revalidate, post-check =0, pre-check=0");

		echo $storage->fileGetContents($filename);
	}

	/* Create/Ovewrite given file with the given raw data */
	public function postFile($storageID, $filename)
	{
		$data    = Request::data();
		$storage = new Storage($storageID);

		return $storage->filePutContents($filename, $data);
	}


	public function deleteFile($storageID, $filename)
	{
		$storage = new Storage($storageID);
		$storage->delete("/.thumbnails/$filename");
		return $storage->delete($filename);
	}

}