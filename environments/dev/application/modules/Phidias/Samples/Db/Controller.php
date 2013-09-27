<?php
use Phidias\Core\Controller;
use Phidias\DB;

use Phidias\DB\Exception\ReferenceNotFound;


class Phidias_Samples_Db_Controller extends Controller
{
    public function main()
    {
        $db = DB::connect('test');

        $peopleTable = new DB\Table('people');

        $peopleTable->addColumn(array(
            'name' => 'id',
            'type' => 'integer',
            'unsigned' => TRUE,
            'autoIncrement' => TRUE
        ));

        $peopleTable->addColumn(array(
            'name' => 'name',
            'type' => 'varchar',
            'length' => 128
        ));

        $peopleTable->setPrimaryKey('id');


        $carsTable = new DB\Table('people_cars');
        $carsTable->setForeignKey('person', $peopleTable, 'id', 'CASCADE', 'CASCADE');
        $carsTable->setPrimaryKey('person');

        $carsTable->addColumn(array(
            'name' => 'car',
            'type' => 'varchar',
            'length' => 128
        ));

        $carsTable->addColumn(array(
            'name' => 'color',
            'type' => 'varchar',
            'length' => 128
        ));



       $db->drop($carsTable);
       $db->drop($peopleTable);

       $db->create($peopleTable);
       $db->create($carsTable);

       $db->insert($peopleTable, array('name'), array(
           array('Santiago'),
           array('Leonardo'),
           array('Esteban'),
           array('Edison'),
           array('Julian')
       ));


       $q = new DB\Select('people');
       $q->leftJoin('people_cars', 'red_cars', 'person', 'id');
       $q->leftJoin('people_cars', 'blue_cars', 'person', 'id', 'blue_cars.color = "blue"');

       $q->field('id', 'people.id')
         ->field('name', 'people.name')
         ->field('blueCar', 'blue_cars.car')
         ->field('redCar', 'blue_cars.car')
         ->field('fullName', 'CONCAT(people.name,1,2,3)');

       $q->where("people.name LIKE 's%'");
       $q->where("people.name LIKE 'sa%'");

       dump($q->toSQL());

       $people = $db->select($q);
       dumpx($people);

    }


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


    public function helpers()
    {
        /* DB helpers */
        $db = DB::connect('test');

        $db->clear('people');

        $res = $db->insert('people', 1, "San'tiago");
        dump($res);

        $res = $db->insert('people', array(
            'id'    => 2,
            'name'  => "San'tiago"
        ));
        dump($res);

        $res = $db->insert('people', array('id', 'name'), array(
            array(3, "San'tiago"),
            array(4, "Es'teban"),
            array(5, "Leo'nardo"),
            array(6, "Ju'lian")
        ));
        dump($res);

        $res = $db->insert('people', NULL, array(
            array(7, "San'tiago"),
            array(8, "Es'teban"),
            array(9, "Leo'nardo"),
            array(10, "Ju'lian")
        ));
        dump($res);

        $res = $db->insert('people', array('name'), array(
            array("San'tiago"),
            array("Es'teban"),
            array("Leo'nardo"),
            array("Ju'lian")
        ));
        dump($res);

        $res = $db->insert('people', array('name'), array(
            array("San'tiago")
        ));
        dump($res);


        $db->delete('people', 'id = 1 OR id = 3 OR id = 5');

        $updated = $db->update('people', array(
            'name'  => "San'tiago editado"
        ), 'id = :id', array('id' => 6));
        dump("$updated actualizados");


        exit;
    }

    public function tables()
    {
        $peopleTable = new DB\Table('people');
        $peopleTable->addColumn(array(
            'name'          => 'id',
            'type'          => 'integer',
            'unsigned'      => TRUE,
            'autoIncrement' => TRUE
        ));

        $peopleTable->addColumn(array(
            'name'          => 'name',
            'type'          => 'varchar',
            'length'        => 128
        ));

        $peopleTable->setPrimaryKey('id');

        $carTable = new DB\Table('people_cars');

        //when setting foreign keys, if the specified column name is not present in the current columns, a new column is added with the types obtained from the foreign column
        $carTable->setForeignKey('person', $peopleTable, 'id');

        $carTable->addColumn(array(
            'name'          => 'car',
            'type'          => 'varchar',
            'length'        => 64
        ));
        $carTable->setPrimaryKey('person');

        $db = DB::connect('test');

        $db->drop($carTable);
        $db->drop('people');

        $db->create($peopleTable);
        $db->create($carTable);

        //$db->insert($peopleTable/*, any of the available insert options */);

    }
}