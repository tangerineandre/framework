<?php
use Phidias\Core\Controller;
use Phidias\DB;

use Phidias\DB\Exception\ReferenceNotFound;


class Phidias_Samples_Db_Controller extends Controller
{
    public function transactions()
    {
        $db = DB::connect('test');

        $db->query("DELETE FROM people_cars");
        $db->query("DELETE FROM people");

        //$db->query("INSERT INTO people VALUES (1, 'Santiago')");


        $db->beginTransaction();
        try {

            $db->query("INSERT INTO people_cars VALUES (1, 1, 'Clio')");
            $db->commit();

        } catch (ReferenceNotFound $e) {

            dump("oops! creando a Santiago!");
            $db->query("INSERT INTO people VALUES (1, 'Santiago')");
            $db->query("INSERT INTO people_cars VALUES (1, 1, 'Clio')");
            $db->commit();

        } catch (Exception $e) {

            $db->rollback();
            dump($e);

        }


        exit('done');
    }

    public function helpers()
    {
        $db->insert('people', array(
            'id'    => 1,
            'name'  => 'Santiago'
        ));


        $people = Person_Entity::collection()
                    ->attr('name')
                    ->attr('carsWithA', Person_Car_Entity::collection()
                            ->attr('car')
                            ->where('carsWithA.car LIKE :condicion', array(
                                'condicion' => "a%"
                            ))
                     )
                    ->attr('carsWithB', Person_Car_Entity::collection()
                            ->attr('car')
                            ->where('carsWithB.car LIKE :condicion', array(
                                'condicion' => "b%"
                            ))
                     );


        foreach ($people as $person) {

            foreach ($person->carsWithA as $car) {
                 //....
            }

            foreach ($person->carsWithB as $car) {
                //....
            }


        }

        $period = new Period_Entity;
        $period->setValues($JSON);


        $period = new Period_Entity;
        $period->name = "2012 - 2013";

        $period->type = new Period_Type_Entity;
        $period->type->name = new Noun_Entity;
        $period->type->name->singular = "Ano academico";
        $period->type->name->plural = "Anos academicos";
        $period->type->name->gender = 1;

        $period->add($period);


    }

    public function exceptions()
    {
        /* Ok then, let's try all exceptions... */

        //connect exception
        try {
            $db = DB::connect('test');
        } catch (Phidias\DB\Exception\CannotConnect $e) {
            dump("No me puedo conectar!");
            dumpx($e);
        }


        //SELECT errors

        //no such table
        try {
            $db->query("SELECT * FROM unaTablaQueNoExiste");
        } catch (Exception $e) {
            dump(get_class($e));
        }

        //unknown column
        try {
            $db->query("SELECT age FROM people");
        } catch (Exception $e) {
            dump(get_class($e));
        }

        //syntax error
        try {
            $db->query("SELECT mun, FROM tryme");
        } catch (Exception $e) {
            dump(get_class($e));
        }


        //INSERT ERRORS

        //duplicate key
        try {
            $db->query("INSERT INTO `people` (`id`) VALUES (1)");
        } catch (Exception $e) {
            dump(get_class($e));
        }

        //cannot be null
        try {
            $db->query("INSERT INTO `people` (`id`) VALUES (NULL)");
        } catch (Exception $e) {
            dump(get_class($e));
        }

        //unkown column on insert
        try {
            $db->query("INSERT INTO `people` (`wtf`) VALUES (NULL)");
        } catch (Exception $e) {
            dump(get_class($e));
        }


        //normal insert.  nothing will be thrown
        try {
            $db->query("DELETE FROM people_cars");
            $db->query("DELETE FROM people");
            $db->query("INSERT INTO `people` (`id`, `name`) VALUES (1, 'santiago')");
            $db->query("INSERT INTO `people` (`id`, `name`) VALUES (2, 'esteban')");
            $db->query("INSERT INTO `people` (`id`, `name`) VALUES (3, 'pedro')");
            $db->query("INSERT INTO `people_cars` (`id`, `person`, `car`) VALUES (1, 1, 'Clio')");
            dump("insertion without errors");
        } catch (Exception $e) {
            dumpx($e);
            dump(get_class($e));
        }

        //unkown foreign entity on insert
        try {
            $db->query("INSERT INTO `people_cars` (`person`, `car`) VALUES (999, 'Clio')");
        } catch (Exception $e) {
            dump(get_class($e));
        }

        //unkown foreign entity on update
        try {
            $db->query("UPDATE `people_cars` SET person = 999 WHERE id = 1");
        } catch (Exception $e) {
            dump(get_class($e));
        }

        //foreign key constraint (try to delete a row referenced in another table)
        try {
            $db->query("DELETE FROM people WHERE id = 1");
        } catch (Exception $e) {
            dump(get_class($e));
        }

    }
}