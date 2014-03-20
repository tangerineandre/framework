<?php

namespace Phidias\Developer;


use Phidias\Core\Person\Entity as Person;
use Phidias\Core\Group\Entity as Group;
use Phidias\Core\Group\Inscription\Entity as Inscription;

class Controller extends \Phidias\Resource\Controller
{
	public function getTest()
	{

		$people = Person::collection()
			->attrs('firstName', 'lastName1', 'lastName2')
			->attr('inscriptions', Inscription::collection())
			->where('id IS NOT NULL AND inscriptions.id IS NOT NULL')
			->set('email', 'NULL')
			->update();

		return $people;
	}
}