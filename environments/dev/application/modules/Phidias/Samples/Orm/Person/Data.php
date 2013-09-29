<?php
namespace Phidias\Samples\Orm;

class Person_Data extends Phidias\ORM\Entity
{
    var $id;
    var $person;
    var $address;
    var $phone;
    var $mobile;
    var $email;

    protected static $map = array(

        'db'    => 'test',
        'table' => 'people_data',

        /* Map attributes to table columns */
        'attributes' => array(

            'id' => array(
                'primary'       => TRUE,
                'name'          => 'id',    //If the column name is not present, the attribute name is used
                'type'          => 'integer',
                'unsigned'      => TRUE,
                'autoIncrement' => TRUE
            ),

            'address' => array(
                'type'      => 'text',
                'length'    => 128,
                'null'      => TRUE
            ),

            'phone' => array(
                'type'      => 'text',
                'length'    => 128,
                'null'      => TRUE
            ),

            'mobile' => array(
                'type'      => 'text',
                'length'    => 128,
                'null'      => TRUE
            ),

            'email' => array(
                'type'      => 'text',
                'length'    => 128,
                'null'      => TRUE
            )
        ),

        'relations' => array(
            'person' => array(
                'entity'    => 'Phidias\Samples\Orm\Person',
                'attribute' => 'id',
            )
        )

    );

}