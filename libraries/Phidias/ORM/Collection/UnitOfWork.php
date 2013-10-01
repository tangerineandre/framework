<?php
namespace Phidias\ORM\Collection;

class UnitOfWork
{
    private $db;
    private $map;

    private $pile;
    private $maxFlushSize;
    private $insertCount;

    public function __construct($db, $map)
    {
        $this->db            = $db;
        $this->map           = $map;

        $this->pile          = array();
        $this->maxFlushSize  = 10000;
        $this->insertCount   = 0;
    }

    public function clear()
    {
        $this->pile = array();
    }

    public function add($entity)
    {
        $this->pile[] = $this->toRecord($entity);

        $pileSize = count($this->pile);
        if ($pileSize >= $this->maxFlushSize ) {
            $this->save();
        }
    }

    private function toRecord($entity)
    {
        $record = array();
        foreach ($this->map['attributes'] as $attributeName => $attributeData) {

            if (!isset($entity->$attributeName)) {
                $record[$attributeData['column']] = NULL;
                continue;
            }

            if ($entity->$attributeName instanceof \Phidias\ORM\Entity) {
                $entity->$attributeName = $entity->$attributeName->getPrimaryKeyValues()[0];
            }

            $record[$attributeData['column']] = $entity->$attributeName;
        }

        return $record;
    }


    public function save()
    {
        $columnNames        = array();
        foreach ($this->map['attributes'] as $attributeName => $attributeData) {
            $columnNames[$attributeData['column']] = $attributeData['column'];
        }

        foreach ($this->map['relations'] as $relationName => $relationData) {
            $columnNames[$relationName] = $relationName;
        }

        $this->insertCount += $this->db->insert($this->map['table'], $columnNames, $this->pile);
        $this->clear();

        return $this->insertCount;
    }
}