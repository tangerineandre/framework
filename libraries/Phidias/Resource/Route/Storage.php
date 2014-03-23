<?php
/*
Esta estructura de datos permite almacenar objetos (y variables de cualquier tipo) (a.k.a. "payload")
y asociarla a atributos.

Luego puede ser consultada con una busqueda de atributos para retornar los PAYLOADS que coincidan con la busqueeda.

e.g.

$foo = new Route\Storage(array(
    'requestMethod'     => 1,
    'resourcePattern'   => 2,
    'controller'        => 2
));

$foo->useCompareFunction('requestMethod', some invocable ($storedValue, $queryValue));
$foo->useCompareFunction('resourcePattern', some invocable ($storedValue, $queryValue));

$foo->store($controller, array(
    'requestMethod'   => 'any value,
    'resourcePattern' => 'any other value'
));


$candidates = $foo->retrieve(array(
    'requestMethod'   => 'any value,
    'resourcePattern' => 'any other value'
));

will return a list of stored objects that match the queried attributes ordered by priority

..............................

*/

namespace Phidias\Resource\Route;

class Storage
{
	private $table            = array();
	private $attributes       = array();
	private $compareFunctions = array();

	/* Array of attributes and their corresponding priorities: 
	e.g.
	array(
		'requestMethod'		=> 1,
		'resourcePattern'	=> 2,
		'controller'		=> 2,
		'attributeFoo'		=> 3
	)
	*/
	public function __construct($attributes)
	{
		$this->attributes = $attributes;
	}

	public function useCompareFunction($attributeName, $compareFunction)
	{
		if (isset($this->attributes[$attributeName]) && is_callable($compareFunction)) {
			$this->compareFunctions[$attributeName] = $compareFunction;
		} else {
			trigger_error("given compare function is not callable", E_USER_ERROR);
		}
	}

	public function store($payload, $attributeValues)
	{
		$record               = array();
		$record['payload']    = $payload;
		$record['attributes'] = array();
		
		$recordKey            = uniqid();

		foreach ($attributeValues as $attributeName => $attributeValue) {
			if (!isset($this->attributes[$attributeName])) {
				continue;
			}
			$record['attributes'][$attributeName] = $attributeValue;
		}

		$this->table[$recordKey] = $record;
	}


	public function retrieve($queryAttributes)
	{
		$candidates = array();
		$orderGuide = array();
		$orderScore = 0;

		foreach ($this->table as $id => $record) {
			$matchScore = $this->getMatchScore($record['attributes'], $queryAttributes);

			if ($matchScore === 0) {
				continue;
			}

			$candidates[$id] = $record['payload'];
			$orderGuide[$id] = $matchScore * 10000  +  ($orderScore++);
		}

		$sortedCandidates = array();
		arsort($orderGuide);

		foreach ($orderGuide as $recordId => $scope) {
			$sortedCandidates[$recordId] = $candidates[$recordId];
		}

		return $sortedCandidates;
	}

	public function getRecordAttributes($recordId)
	{
		return isset($this->table[$recordId]) ? $this->table[$recordId]['attributes'] : NULL;
	}


	private function getMatchScore($storedAttributes, $queryAttributes)
	{
		$sumPriority = 0;

		foreach ($storedAttributes as $attributeName => $storedAttributeValue) {

			if ($storedAttributeValue === NULL) {
				$sumPriority += $this->attributes[$attributeName];
				continue;
			}

			if (!isset($queryAttributes[$attributeName])) {
				return 0;
			}

			$queryAttributeValue = $queryAttributes[$attributeName];
			$matchingFunction	 = isset($this->compareFunctions[$attributeName]) ? $this->compareFunctions[$attributeName] : NULL;

			$matchScore = $this->valuesMatch($storedAttributeValue, $queryAttributeValue, $matchingFunction);

			if (!$matchScore) {
				return 0;
			}

			$sumPriority += $matchScore * $this->attributes[$attributeName];
		}

		return $sumPriority;
	}


	private function valuesMatch($storedValue, $queryValue, $function = NULL)
	{
		if ($function === NULL) {
			return $storedValue == $queryValue;
		}

		return call_user_func_array($function, array($storedValue, $queryValue));
	}
}