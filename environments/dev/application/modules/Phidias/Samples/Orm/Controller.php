<?php
use Phidias\Core\Controller;

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

            $outgoingCount = rand(1, 10);
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
        $people = Person::collection()
                ->allAttributes()
                ->attr('incomingMessages', Message::collection()
                    ->relatedWith('to')
                    ->allAttributes()
                    ->attr('autor', Person::single()
                            ->relatedWith('from')
                            ->allAttributes()
                    )

                )
                ->limit(50)
                ->find();

        foreach ($people as $person) {
            dump("Persona: $person->firstName $person->lastName");

            dump("Mensajes recibidos:");
            foreach ($person->incomingMessages as $message) {
                dump("$message->subject: $message->body");
                dump("Enviado por: {$message->autor->firstName} {$message->autor->lastName}");
                dump("-------");
            }
            dump("---  ----");

        }


        $allMessages = Message::collection()
                ->allAttributes()
                ->attr('sender', Person::single()
                    ->relatedWith('from')
                    ->allAttributes()
                )

                ->attr('receiver', Person::single()
                    ->relatedWith('to')
                    ->allAttributes()
                )

                ->limit(50)
                ->find();

        foreach($allMessages as $message) {
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
        /*
         * Collection sintax:
         * A special collection may be defined to handle single elements:
         */

        //Fetching an object by ID:
        $personID = 1234;
        $person = Person::single($personID)->allAttributes()->find();
        if ($person === NULL) {
            dump("$personID not found.  Moving on ...");
        } else {
            dump('Found');
            dump($person);
        }

        //Inserting:
        $person             = new Person;
        $person->firstName  = 'Nuevo';
        $person->lastName   = 'Personaje';
        $person->gender     = 1;

        Person::collection()->add($person)->save();
        $lastInsertId = Person::collection()->getInsertID();
        dump("Created person: $lastInsertId");

        //Updating:
        $count = Person::single($lastInsertId)->set('firstName', 'EDITADO')->update();
        dump("Updated $count records");

        //Deleting
        $count = Person::single($lastInsertId)->delete();
        dump("Deleted $count records");



        /*
         * Entity syntax:
         * The Entity class provides wrappers around all of the above methods:
         */

        //Fetching:
        try {
            $person = new Person($personID);
            dump('Found');
            dump($person);
        } catch (Phidias\ORM\Exception\EntityNotFound $e) {
            dump("$personID not found.  Moving on ...");
        }

        //Inserting:
        $person             = new Person;
        $person->firstName  = 'Nuevo';
        $person->lastName   = 'Personaje';
        $person->gender     = 1;
        $person->save();
        dump("Created person: $person->id");

        //Updating:
        $person->firstName = 'EDITADO';
        $count = $person->update();
        dump("Updated $count records");

        //Deleting:
        $count = $person->delete();
        dump("Deleted $count records");


        exit;
    }

    public function nesting()
    {
        //Nested insertion:
        $message = new Message;
        $message->subject = 'Hello world';

        $message->from = new Person;
        $message->from->firstName   = 'Some';
        $message->from->lastName    = 'Author';
        $message->from->gender      = 1;

        $message->to = new Person(123);

        $message->save();

    }


    public function count()
    {
        dump(Person::collection()->count()." people");
        dump(Message::collection()->count()." messages");
    }
}
