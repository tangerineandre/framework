<?php
namespace Phidias\ORM\Collection;

use Phidias\Core\Configuration;

class UnitOfWork
{
    private $attributes;
    private $joins;
    private $map;
    private $db;

    private $pile;
    private $maxFlushSize;
    private $insertCount;

    public function __construct($attributes, $joins, $map, $db)
    {
        $this->attributes   = $attributes;
        $this->joins        = $joins;
        $this->map          = $map;
        $this->db           = $db;

        $this->pile          = array();
        $this->maxFlushSize  = Configuration::get('orm.collection.maxFlushSize');
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
            $columnNames[] = $this->map->getColumn($attributeName);
        }

        $this->insertCount += $this->db->insert($this->map->getTable(), $columnNames, $this->pile);
        $this->clear();

        return $this->insertCount;
    }

    private function toRecord($object)
    {
        $record = array();

        $object = (array)$object;

        /* Resolve nestings */
        foreach ($this->joins as $attributeName => $joinData) {

            if (!isset($object[$attributeName])) {
                continue;
            }

            $nestedMap     = $joinData['collection']->getMap();
            $expectedKey   = $nestedMap['keys'][0];
            $joinData['collection']->add($object[$attributeName]);

            try {
                $joinData['collection']->save();
                $object[$attributeName] = $nestedMap->isAutoIncrement($expectedKey) ? $joinData['collection']->getInsertID() : $object[$attributeName][$expectedKey];
            } catch (\Phidias\DB\Exception\DuplicateKey $e) {
                $object[$attributeName] = $object[$attributeName][$expectedKey];
            }

        }

        foreach (array_keys($this->attributes) as $attributeName) {

            $targetColumn = $this->map->getColumn($attributeName);

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