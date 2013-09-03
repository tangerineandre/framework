<?php
namespace Phidias\Samples\Orm;

use Phidias\ORM\Entity;

class Person_Color extends Entity
{
    var $id;
    var $color;
    var $person;

    protected static $_schema = array(

        'db'    => 'test',
        'table' => 'people_colors',

        'keys' => array(
            'id' => array(
                'type'          => 'integer',
                'unsigned'      => TRUE,
                'autoIncrement' => TRUE
            )
        ),

        'attributes' => array(
            'color' => array(
                'type'      => 'varchar',
                'length'    => 64
            )
        ),

        'relations' => array(
            'person' => array(
                'entity'    => 'Phidias\Samples\Orm\Person'
            )
        )

    );
}