<?php
namespace Phidias\Samples\Orm;

class Person extends \Phidias\ORM\Entity
{
    var $id;
    var $firstName;
    var $lastName;
    var $gender;
    var $birthDay;

    /* A map maps entity attributes to table columns */
    protected static $map = array(

        'db'    => 'test',
        'table' => 'people',

        'keys' => array('id'),

        'attributes' => array(

            'id' => array(
                'type'          => 'integer',
                'unsigned'      => TRUE,
                'autoIncrement' => TRUE
            ),

            'firstName' => array(
                'name'      => 'first_name',
                'type'      => 'varchar',
                'length'    => 128
            ),

            'lastName' => array(
                'name'      => 'last_name',
                'type'      => 'varchar',
                'length'    => 128
            ),

            'gender' => array(
                'type'      => 'integer',
                'length'    => 1,
                'unsigned'  => TRUE,
                'default'   => 1
            ),

            'birthDay' => array(
                'name'          => 'birthday',
                'type'          => 'integer',
                'acceptNull'    => TRUE,
                'default'       => NULL
            )
        )

    );

}