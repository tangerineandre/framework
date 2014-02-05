<?php
namespace Phidias\Component;

interface StorageInterface
{
    public function __construct($root);
    public function getList($source = "/");
    public function isFile($filename);
	public function isDirectory($directory);
    public function filePutContents($filename, $data);
    public function fileGetContents($filename);
    public function copy($source, $destination);
    public function move($source, $destination);
    public function delete($filename);
    public function upload($localFile, $destination);
    public function getUrl($filename);
    public function destroy();
}