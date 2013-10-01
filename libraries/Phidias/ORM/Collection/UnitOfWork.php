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
        $this->pile[] = (array)$entity;

        $pileSize = count($this->pile);
        if ($pileSize >= $this->maxFlushSize ) {
            $this->save();
        }
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