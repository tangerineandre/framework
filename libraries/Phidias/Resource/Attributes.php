<?php
namespace Phidias\Resource;

class Attributes extends \Phidias\HashTable
{
    public function collectionHelper($collection)
    {
        return new Attributes\CollectionHelper($this, $collection);
    }
}
