<?php
use Phidias\Core\Controller;

use Phidias\Component\Navigation;

use Phidias\Samples\Orm\Person;
use Phidias\Samples\Orm\Person_Data;
use Phidias\Samples\Orm\Person_Color;

class Phidias_Samples_Orm_Controller extends Controller
{
    public function table()
    {
        $collection = Person::collection()
                        ->attr('id')
                        ->allAttributes()
                        ->filter(array(
                            'birthDay' => function($value) {
                                return date('d F Y', $value);
                            }
                        ));
                        //->attr('colors', Person_Color::collection()->allAttributes());

        $dataTable = new Phidias\ORM\DataTable($collection);

        $dataTable
            ->column('ID', 'id')
            ->column('first name', 'firstName')
            ->column('last name', 'lastName')
            ->column('birth date', 'birthDay')
            ->column('gender', 'gender', function($person) {
                return $person->gender ? '<span style="color: pink">Girl</span>' : 'Boy';
            });

            /*->column('colors', NULL, function($person) {
                $colors = array();
                foreach($person->colors as $color) {
                    $colors[] = $color->color;
                }
                return implode(', ', $colors);
            });*/


        $dataTable->filter($this->attributes->get());

        $this->set('dataTable', $dataTable);
        $this->useView('phidias/orm/dataTable');
    }

    public function main()
    {
        $person = Person::single(500)
                ->attrs('firstName', 'lastName', 'gender')
                ->attr('data', Person_Data::single()->allAttributes())
                ->find();

        dump($person->toArray());
        dump($person->data->toArray());
    }

    public function save()
    {
        $person             = new Person;
        $person->firstName  = 'Santiago';
        $person->lastName   = 'O\'Hara';
        $person->gender     = 1;
        $person->birthday   = mktime(0, 0, 0, 1, 15, 1982);

        $person->save();
    }

    public function deleteall()
    {
        $people = Person::collection()
                ->allAttributes()
                ->where("Person.firstName = 'Santiago'")
                ->find();

        foreach ($people as $person) {
            dump($person);
            $person->delete();
        }
    }

    public function update($id)
    {
        $person = Person::single($id)
                ->attr('firstName')
                ->find();

        $person->firstName  = 'editado '.rand(1,123456);
        $person->lastName   = 'editado';

        dump($person->update());
    }

    public function updatecollection()
    {
        $names = array('John', 'Jack', 'Pete', 'Dave');

        $updated = Person::collection()
                    ->attr('firstName', ":randomName", array('randomName' => $names[rand(0,count($names)-1)]))
                    ->attr('lastName', 'CONCAT("Mc",Person.id,"th")')
                    ->attr('birthDay', rand(0, 946684800))

                    ->where("Person.id <= 50")

                    ->update();

        dump("$updated records updated");
    }

    public function deletecollection()
    {
        $deleted = Person::collection()
                    ->where("Person.lastName LIKE '%3'")
                    ->delete();

        dump("$deleted records deleted");
    }


    public function single($id)
    {
        $person = Person::single($id)
                ->allAttributes()
                ->find();

        if ($person === NULL) {
            echo "$id not found";
            return;
        }

        dump($person);
        echo '<hr />';

        $person = Person::single($id)
                ->allAttributes()
                ->attr('data', Person_Data::single()
                    ->allAttributes()
                )
                ->find();
        dump($person);
        echo '<hr />';


        $person = Person::single($id)
                ->allAttributes()
                ->attr('data', Person_Data::single()
                    ->allAttributes()
                )
                ->attr('colors', Person_Color::collection()
                    ->allAttributes()
                )
                ->find();
        dump($person);

        echo '<ul>';
        foreach ( $person->colors as $color ) {
            echo '<li>';
            dump($color);
            echo '</li>';
        }
        echo '</ul>';
        echo '<hr />';
    }

    public function collection()
    {
        $people = Person::collection()
                ->attrs('firstName', 'lastName', 'birthDay')
                ->attr('age', 'FLOOR((:now - Person.birthDay)/31557600)', array('now' => time()))
                ->attr('extradata', Person_Data::single()->allAttributes())
                ->attr('colors', Person_Color::collection()->allAttributes())

                ->limit(2000)
                ->find();

        foreach ($people as $person) {
            dump("$person->firstName $person->lastName $person->age ".date('d M Y', $person->birthDay));
            dump("Sus colores son: ");
            foreach ($person->colors as $colordata) {
                dump($colordata->color);
            }
        }

    }


    public function rebuild()
    {
        $engine = $this->attributes->get('engine', 'InnoDB');

        Person_Color::table()->drop();
        Person_Data::table()->drop();
        Person::table()->drop();

        Person::table()->create($engine);
        Person_Data::table()->create($engine);
        Person_Color::table()->create($engine);
    }

    public function populate()
    {
        $size = $this->attributes->get('size', 1000000);

        Person_Color::table()->truncate();
        Person_Data::table()->truncate();
        Person::table()->truncate();

        $people = Person::collection();

        for ( $cont = 1; $cont <= $size; $cont++ ) {
            $person             = new Person;
            $person->firstName  = "John $cont";
            $person->lastName   = "Mc$cont";
            $person->gender     = rand(0,1);
            $person->birthDay   = rand(0, time());

            $people->add($person);
        }
        $people->save();


        $datas = Person_Data::collection();
        for ( $cont = 1; $cont <= $size; $cont++ ) {
            $data           = new Person_Data;
            $data->id       = $cont;
            $data->address  = "Fake St. No. $cont - $cont";
            $data->phone    = "$cont-123456";
            $data->mobile   = "313-$cont-123456";
            $data->email    = "john$cont@test.com";

            $datas->add($data);
        }
        $datas->save();


        $colorNames = array(
            'red', 'orange', 'yellow', 'green', 'blue', 'purple'
        );

        $colors = Person_Color::collection();
        for ( $cont = 1; $cont <= $size; $cont++ ) {

            $nColors = rand(0,4);
            for ( $colorCont = 1; $colorCont <= $nColors; $colorCont++ ) {
                $color          = new Person_Color;
                $color->person  = $cont;
                $color->color   = $colorNames[rand(0,count($colorNames)-1)];

                $colors->add($color);
            }
        }
        $colors->save();

    }
}
