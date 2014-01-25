<?php
use Phidias\Core\Controller;

use Phidias\DB\Select;

class Phidias_Test_Controller extends Controller
{
    public function main()
    {
        $period = new Select('nodes', 'p0');
        $type = new Select('types', 't0');
        $noun = new Select('nouns', 'n0');
        $type->join('left', $noun, "t0.name = n0.singular");
        $period->join('left', $type, "p0.type = t0.id");

        $period->field('name', "CONCAT(n0.singular, ' ', p0.name)");

        dump($period->toSQL());



        $select = new Select('people');
        $select->field('firstName', 'people.firstname');
        $select->field('lastName', 'people.lastname1');
        $select->field('fullName', "CONCAT(people.firstname, ' ', people.lastname1)");

        $channel = new Select('people_channels');
        $channel->field('channel');
        $channel->field('value');
        $select->join('left', $channel, "people_channels.person = people.id");

        $select->order('fullName');
        $select->groupBy('fullName');

        dump($select->toSQL());

        exit;
    }

}