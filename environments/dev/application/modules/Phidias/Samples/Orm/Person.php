<?php
namespace Phidias\Samples\Orm;

use Phidias\ORM\Entity;

class Person extends Entity
{
    var $id;
    var $firstName;
    var $lastName;
    var $gender;
    var $birthDay;
    var $color;

    protected static $_schema = array(

        'db'    => 'test',
        'table' => 'people',

        'keys' => array(
            'id' => array(
                'type'          => 'integer',
                'unsigned'      => TRUE,
                'autoIncrement' => TRUE
            )
        ),

        'attributes' => array(
            'firstName' => array(
                'column'    => 'first_name',
                'type'      => 'varchar',
                'length'    => 128
            ),

            'lastName' => array(
                'column'    => 'last_name',
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
                'column'    => 'birthday',
                'type'      => 'integer',
                'null'      => TRUE
            )
        )

    );
}