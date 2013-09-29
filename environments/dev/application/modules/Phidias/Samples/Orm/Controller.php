<?php
use Phidias\Core\Controller;

use Phidias\Component\Navigation;

use Phidias\Samples\Orm\Person;
use Phidias\Samples\Orm\Person_Data;
use Phidias\Samples\Orm\Person_Color;

use Phidias\Samples\Orm\Message;

class Phidias_Samples_Orm_Controller extends Controller
{
    public function install()
    {
        $personTable = Person::table();
        $messageTable = Message::table();

        $messageTable->drop();
        $personTable->drop();

        $personTable->create();
        $messageTable->create();
    }

    public function populate()
    {
        $size = $this->attributes->get('size', 10000);

        $collection = Person::collection();
        for($cont = 1; $cont <= $size; $cont++) {
            $person = new Person;
            $person->firstName = "Person $cont";
            $person->lastName = "Mc. $cont";
            $person->gender = rand(0,1);
            $person->birthDay = rand(1000000,10000000);
            $collection->add($person);
        }
        $total = $collection->save();
        dump("Inserted $total people");

        $messages = Message::collection();
        for ($cont = 1; $cont <= $size; $cont++) {

            $outgoingCount = rand(1, 20);
            for ($i = 1; $i <= $outgoingCount; $i++) {
                $message            = new Message;
                $message->from      = $cont;
                $message->to        = rand(1, $size);
                $message->subject   = "From $message->from to $message->to";
                $message->body      = "From $message->from to $message->to";

                $messages->add($message);
            }

        }

        $total = $messages->save();
        dump("Inserted $total messages");
    }

    public function iterate()
    {
        /* Assuming Message entity has two relations with Person: "from" and "to" */

        $people = Person::collection()
                ->allAttributes()
                ->attr('incomingMessages', Message::collection()
                    ->relatedWith('to')
                    ->allAttributes()
                )
                ->limit(500)
                ->find();

        foreach ($people as $person) {
            $person->incomingMessages = $person->incomingMessages->fetchAll();
            dump($person);
        }


        $outgoingMessages = Message::collection()
                ->allAttributes()
                ->attr('sender', Person::single()
                    ->relatedWith('from')
                    ->allAttributes()
                )

                ->attr('receiver', Person::single()
                    ->relatedWith('to')
                    ->allAttributes()
                )

                ->limit(10)
                ->find();

        foreach($outgoingMessages as $message) {
            dump($message);
        }

    }

    public function update()
    {
        $updated = Message::collection()
                    ->set('deleteDate', 123456)
                    ->where('Message.to = 9995')
                    ->update();
        dump("Updated $updated messages");

        $deleted = Message::collection()
                    ->where('Message.to = 10000')
                    ->delete();
        dump("Deleted $deleted messages");
    }

    public function delete()
    {
        $deleted = Person::collection()
                    ->where('Person.lastName = :name', array('name' => 'personaje'))
                    ->delete();
        dump("Deleted $deleted people");
    }

    public function single()
    {
        $person = new Person;
        $person->firstName = 'Nuevo';
        $person->lastName = 'Personaje';
        $person->gender = 1;
        $person->save();

        dump($person);


        $person->firstName = 'Editado';
        $person->update();


        dump($person->delete());

        return;

        $person = new Person(123);
        $person->firstName = 'editado';
        $person->update();
    }

    public function count()
    {
        dump(Person::collection()->count()." people");
        dump(Message::collection()->count()." messages");
    }
}
