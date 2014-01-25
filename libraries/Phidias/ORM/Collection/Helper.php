<?php
namespace Phidias\ORM\Collection;

class Helper
{
	private $collection;

	public function __construct($collection)
	{
		$this->collection = $collection;
	}

	/* Establish collection order from incoming attributes */
	public function searchFromAttributes($attributes, $searchFields)
	{
        $query = $attributes->get('query');
        if (trim($query)) {
            $this->collection->search($query, $searchFields);
        }
	}

	/* Establish collection order from incoming attributes */
	public function orderFromAttributes($attributes)
	{
        $orderAttribute = $attributes->get('order');
        if ($orderAttribute !== null) {
            $this->collection->orderBy($orderAttribute, $attributes->get('descending', false));
        }
	}

	/* Establish collection limit, page and offset from incoming attributes */
	public function paginationFromAttributes($attributes, $defaultLimit = 50)
	{
        $limit = $attributes->get('limit', $defaultLimit);
        $this->collection->limit((int)$limit);

        $page = $attributes->get('page');
        if ($page !== null) {
            $this->collection->page((int)$page);
        }

        $offset = $attributes->get('offset');
        if ($offset !== null) {
            $this->collection->offset((int)$offset);
        }

	}
}