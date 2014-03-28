<?php
namespace Phidias;

class HashTable
{
	private $variables;

	public function __construct($variables = array())
	{
		$this->variables = (array)$variables;
	}

	public function get($variableName = NULL, $defaultValue = NULL)
	{
		if ($variableName === NULL) {
			return $this->getAll();
		}

		return isset($this->variables[$variableName]) ? $this->variables[$variableName] : $defaultValue;
	}

	public function getAll()
	{
		return $this->variables;
	}

	public function required($variableName)
    {
        if (!isset($this->variables[$variableName])) {
            throw new HashTable\Exception\RequiredVariable(array('variable' => $variableName));
        }

        return $this->variables[$variableName];
    }
}