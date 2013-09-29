<?php
namespace Phidias\ORM;

class Entity
{
    protected static $map;

    public function getMap()
    {
        $className = get_called_class();
        return $className::$map;
    }

    public static function collection()
    {
        $className = get_called_class();
        return new Collection(new $className);
    }

    public static function single()
    {
        $className = get_called_class();
        return new Collection(new $className, TRUE);
    }

    public static function table()
    {
        $className = get_called_class();
        return new Table(new $className);
    }

    public function toArray()
    {
        return (array)$this;
    }

    public function toJSON()
    {
        return json_encode($this);
    }


    public function save()
    {
        $className = get_called_class();
        $collection = new Collection(new $className, TRUE);
        $collection->add($this);

        if ($collection->save() == 1) {
            $map = $this->getMap();

            foreach ( $map['keys'] as $keyName ) {
                if ( isset($map['attributes'][$keyName]['autoIncrement']) && $map['attributes'][$keyName]['autoIncrement'] ) {
                    $this->$keyName = $collection->getDB()->getInsertID();
                }
            }

            return TRUE;
        } else {
            return FALSE;
        }
    }

    public function update()
    {
        exit('IMPLEMENT ME!');
    }

    public function delete()
    {
        $className = get_called_class();
        $collection = new Collection(new $className, TRUE);
        return $collection->remove($this);
    }


}