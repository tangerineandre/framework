<?php
namespace Phidias\ORM;

use Phidias\DB;

class Table
{
    private $table;
    private $map;
    private $db;

    private static $lock = array();

    public function __construct($map)
    {
        $this->map      = $map;
        $this->table    = new \Phidias\DB\Table($map['table']);

        foreach ($map['attributes'] as $attributeName => $attributeData) {
            if (!isset($attributeData['column'])) {
                $attributeData['column'] = $attributeName;
            }
            $attributeData['name'] = $attributeData['column'];

            $this->table->addColumn($attributeData);
        }

        $this->table->setPrimaryKey($map['keys']);


        /* Avoid recurson on self-referencing instances */
        if (isset(self::$lock[$map['table']])) {
            return;
        }
        self::$lock[$map['table']] = TRUE;


        if (isset($map['relations'])) {
            foreach($map['relations'] as $relationName => $relationData) {
                $relatedEntity = new $relationData['entity'];
                $onDelete = isset($relationData['onDelete']) ? $relationData['onDelete'] : NULL;
                $onUpdate = isset($relationData['onUpdate']) ? $relationData['onUpdate'] : NULL;
                $this->table->setForeignKey($relationName, $relatedEntity::table()->getDbTable(), $relationData['attribute'], $onDelete, $onUpdate);
            }
        }

        unset(self::$lock[$map['table']]);
    }

    private function getDB()
    {
        if ($this->db === NULL) {
            $this->db = DB::connect(isset($this->map['db']) ? $this->map['db'] : NULL);
        }

        return $this->db;
    }

    public function getDbTable()
    {
        return $this->table;
    }

    public function drop()
    {
        $this->getDB()->drop($this->table);
    }

    public function truncate()
    {
        $this->getDB()->truncate($this->table);
    }

    public function clear()
    {
        $this->getDB()->clear($this->table);
    }

    public function create()
    {
        $this->getDB()->create($this->table);
    }

}