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
        $this->table    = new \Phidias\DB\Table($this->map->getTable());

        foreach ($this->map->getAttributes() as $attributeData) {

            $columnData         = $attributeData;
            $columnData['name'] = $attributeData['column'];

            if (isset($attributeData['entity'])) {
                $foreignMap = $attributeData['entity']::getMap();

                if (!$foreignMap->hasAttribute($attributeData['attribute'])) {
                    trigger_error("related attribute '{$attributeData['attribute']}' not found in entity '{$attributeData['entity']}'", E_USER_ERROR);
                }

                $columnData = array_merge($columnData, $foreignMap->getAttribute($attributeData['attribute']));
                unset($columnData['autoIncrement']);
            }

            $this->table->addColumn($columnData);
        }

        $this->table->setPrimaryKey($this->map->getKeys());


        /* Avoid recurson on self-referencing instances */
        if (isset(self::$lock[$this->map->getTable()])) {
            return;
        }
        self::$lock[$this->map->getTable()] = TRUE;

        foreach($this->map->getRelations() as $relationData) {

            $relatedEntity  = new $relationData['entity'];
            $onDelete       = isset($relationData['onDelete']) ? $relationData['onDelete'] : NULL;
            $onUpdate       = isset($relationData['onUpdate']) ? $relationData['onUpdate'] : NULL;

            $this->table->setForeignKey($relationData['column'], $relatedEntity::table()->getDbTable(), $relationData['attribute'], $onDelete, $onUpdate);
        }

        foreach ($this->map->getIndexes() as $name => $columns) {
            $this->table->addIndex($name, $columns);
        }

        unset(self::$lock[$this->map->getTable()]);
    }

    private function getDB()
    {
        if ($this->db === NULL) {
            $this->db = DB::connect($this->map->getDB());
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

    public function defragment()
    {
        $tableName = $this->table->getName();
        $this->getDB()->query("ALTER TABLE `$tableName` ENGINE = InnoDB");
    }

    public function optimize()
    {
        $tableName = $this->table->getName();
        $this->getDB()->query("OPTIMIZE TABLE `$tableName`");
    }

    public function create($engine = 'InnoDB')
    {
        $this->getDB()->create($this->table, $engine);

        /* Create triggers */
        $tableName  = $this->map->getTable();
        $triggers   = $this->map->getTriggers();
        foreach ($triggers as $operation => $actions) {
            foreach ($actions as $when => $code) {

                if (!$code) {
                    continue;
                }

                $triggerName = "{$tableName}_{$when}_{$operation}";

                $this->getDB()->query("DROP TRIGGER IF EXISTS `$triggerName`");
                $this->getDB()->query("CREATE TRIGGER `$triggerName` $when $operation ON `$tableName`
                    FOR EACH ROW
                    BEGIN
                        IF (@DISABLE_TRIGGERS IS NULL) then
                            $code
                        END IF;
                    END
                ");

            }
        }

    }

}