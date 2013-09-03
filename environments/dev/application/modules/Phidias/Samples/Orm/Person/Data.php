<?php
namespace Phidias\Samples\Orm;

use Phidias\ORM\Entity;

class Person_Data extends Entity
{
    var $id;
    var $address;
    var $phone;
    var $mobile;
    var $email;

    protected static $_schema = array(

        'db' => 'test',
        'table' => 'people_data',

        'keys' => array(
            'id' => array(
                'type'      => 'integer',
                'unsigned'  => TRUE,
            )
        ),

        'attributes' => array(
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
            ),

        ),

        'relations' => array(
            'person' => array(
                'column'    => 'id',
                'entity'    => 'Phidias\Samples\Orm\Person'
            )
        )

    );
}