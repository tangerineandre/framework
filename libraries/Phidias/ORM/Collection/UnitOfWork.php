<?php
namespace Phidias\ORM\Collection;

class UnitOfWork
{
    private $attributes;
    private $nestedCollections;
    private $map;
    private $db;

    private $pile;
    private $maxFlushSize;
    private $insertCount;

    public function __construct($collection)
    {
        $this->attributes           = $collection->getAttributes();
        $this->nestedCollections    = $collection->getNestedCollections();
        $this->map                  = $collection->getMap();
        $this->db                   = $collection->getDB();

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

    public function save()
    {
        $columnNames = array();
        foreach (array_keys($this->attributes) as $attributeName) {
            $columnNames[] = $this->map['attributes'][$attributeName]['column'];
        }

        $this->insertCount += $this->db->insert($this->map['table'], $columnNames, $this->pile);
        $this->clear();

        return $this->insertCount;
    }

    private function toRecord($object)
    {
        $record = array();

        $object = (array)$object;

        /* Resolve nestings */
        foreach ($this->nestedCollections as $attributeName => $collectionData) {

            if (!isset($object[$attributeName])) {
                continue;
            }

            $nestedMap     = $collectionData['foreignCollection']->getMap();
            $expectedKey   = $nestedMap['keys'][0];
            $collectionData['foreignCollection']->add($object[$attributeName]);

            try {
                $collectionData['foreignCollection']->save();
                $object[$attributeName] = isset($nestedMap['attributes'][$expectedKey]['autoIncrement']) ? $collectionData['foreignCollection']->getInsertID() : $object[$attributeName][$expectedKey];
            } catch (\Phidias\DB\Exception\DuplicateKey $e) {
                $object[$attributeName] = $object[$attributeName][$expectedKey];
            }

        }

        foreach (array_keys($this->attributes) as $attributeName) {

            $targetColumn = $this->map['attributes'][$attributeName]['column'];

            if (!isset($object[$attributeName]) || is_array($object[$attributeName]) ) {

                $record[$targetColumn] = NULL;

            } elseif ($object[$attributeName] instanceof \Phidias\ORM\Entity) {

                $keyValues = $object[$attributeName]->getPrimaryKeyValues();
                $record[$targetColumn] = array_pop($keyValues);

            } else {

                $record[$targetColumn] = $object[$attributeName];

            }
        }

        return $record;
    }

}