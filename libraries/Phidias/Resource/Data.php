<?php

namespace Phidias\Resource;

class Data
{
	private $model;

	public function __construct($model)
	{
		$this->model = $model;
	}

	public function get($attributeName = NULL, $defaultValue = NULL)
	{
		if ($attributeName === NULL || is_scalar($this->model)) {
			return $this->model;
		}

		if (is_array($this->model)) {
			return isset($this->model[$attributeName]) ? $this->model[$attributeName] : $defaultValue;
		}

		return isset($this->model->$attributeName) ? $this->model->$attributeName : $defaultValue;
	}

}