<?php
namespace Phidias\Resource\Attributes;

class CollectionHelper
{
	private $attributes;
	private $collection;

	public function __construct($attributes, $collection)
	{
		$this->attributes = $attributes;
		$this->collection = $collection;
	}

	/* Establish collection order from incoming attributes */
	public function search($searchFields)
	{
        $query = $this->attributes->get('query');
        if (trim($query)) {
            $this->collection->search($query, $searchFields);
        }

        return $this;
	}

	/* Establish collection order from incoming attributes */
	public function sort()
	{
        $orderAttribute = $this->attributes->get('order');
        if ($orderAttribute !== null) {
            $this->collection->orderBy($orderAttribute, $this->attributes->get('descending', false));
        }

        return $this;
	}

	/* Establish collection limit, page and offset from incoming attributes */
	public function paginate($defaultLimit = 50)
	{
        $limit = $this->attributes->get('limit', $defaultLimit);
        $this->collection->limit((int)$limit);

        $page = $this->attributes->get('page');
        if ($page !== null) {
            $this->collection->page((int)$page);
        }

        $offset = $this->attributes->get('offset');
        if ($offset !== null) {
            $this->collection->offset((int)$offset);
        }

        return $this;
	}
}