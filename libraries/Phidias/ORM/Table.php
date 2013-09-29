<?php
namespace Phidias\ORM;

use Phidias\DB;

class Table
{
    private $entity;
    private $table;
    private $db;

    public function __construct($entity)
    {
        $this->entity = $entity;

        $map = $this->entity->getMap();

        $this->table = new \Phidias\DB\Table($map['table']);
        foreach ($map['attributes'] as $attributeName => $attributeData) {
            if (!isset($attributeData['name'])) {
                $attributeData['name'] = $attributeName;
            }
            $this->table->addColumn($attributeData);
        }

        if (isset($map['relations'])) {
            foreach($map['relations'] as $relationName => $relationData) {
                $relatedEntity = new $relationData['entity'];
                $onDelete = isset($relationData['onDelete']) ? $relationData['onDelete'] : NULL;
                $onUpdate = isset($relationData['onUpdate']) ? $relationData['onUpdate'] : NULL;
                $this->table->setForeignKey($relationName, $relatedEntity::table()->getDbTable(), $relationData['attribute'], $onDelete, $onUpdate);
            }
        }

        $this->table->setPrimaryKey($map['keys']);

        $this->db = DB::connect(isset($map['db']) ? $map['db'] : NULL);
    }

    public function getDbTable()
    {
        return $this->table;
    }

    public function drop()
    {
        $this->db->drop($this->table);
    }

    public function truncate()
    {
        $this->db->truncate($this->table);
    }

    public function clear()
    {
        $this->db->clear($this->table);
    }

    public function create()
    {
        $this->db->create($this->table);
    }

}