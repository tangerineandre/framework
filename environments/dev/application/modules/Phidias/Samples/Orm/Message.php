<?php
namespace Phidias\Samples\Orm;

class Message extends \Phidias\ORM\Entity
{
    var $id;
    var $subject;
    var $body;
    var $deleteDate;
    var $from;
    var $to;

    protected static $map = array(

        'db'    => 'test',
        'table' => 'messages',

        'keys'  => array('id'),

        /* Map attributes to table columns */
        'attributes' => array(

            'id' => array(
                'name'          => 'id',    //If the column name is not present, the attribute name is used
                'type'          => 'integer',
                'unsigned'      => TRUE,
                'autoIncrement' => TRUE
            ),

            'subject' => array(
                'type'          => 'varchar',
                'length'        => 255,
                'acceptNull'    => TRUE
            ),

            'body' => array(
                'type'      => 'mediumtext'
            ),

            'deleteDate' => array(
                'name'          => 'delete_date',
                'type'          => 'integer',
                'acceptNull'    => TRUE,
                'default'       => NULL
            )

        ),

        'relations' => array(
            'from' => array(
                'entity'    => 'Phidias\Samples\Orm\Person',
                'attribute' => 'id',
                'onDelete'  => 'CASCADE',
                'onUpdate'  => 'CASCADE'
            ),

            'to' => array(
                'entity'    => 'Phidias\Samples\Orm\Person',
                'attribute' => 'id',
                'onDelete'  => 'CASCADE',
                'onUpdate'  => 'CASCADE'
            ),
        )

    );

}