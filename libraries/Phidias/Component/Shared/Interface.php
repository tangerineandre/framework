<?php
namespace Phidias\Component;

interface Shared_Interface
{
    public function __construct($shareName);
    public function get($key, $defaultValue = NULL);
    public function set($key, $value);
    public function delete($key);
    public function destroy();
}