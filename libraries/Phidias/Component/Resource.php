<?php
namespace Phidias\Component;

class Resource implements ResourceInterface
{

	private $URI;
	private $attributes;

	public function __construct($URI, $attributes = NULL)
	{
		$this->uri = $this->sanitizeURI($URI);
	}

	/* Sanitize and set resource's URI  */
	private function sanitizeURI($URI)
	{
		return rtrim($URI,'/');
	}


	public function acceptContentType($mimeTypes)
	{

	}





	/* Perform the given request method on the resource */
	public function run($requestMethod = NULL)
	{

	}

}