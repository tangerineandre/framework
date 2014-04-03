<?php
namespace Phidias\Example;

class myController extends \Phidias\Resource\Controller
{
	public function hello()
	{
		$aja = function($a, $b) {
			$a = 1;
			$b = 2;
			dumpx('aja');
		};

		$aja(1, 2);

		return "Hello";
	}
}