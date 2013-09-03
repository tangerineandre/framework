<?php
namespace Phidias\Component;

class Shared_Dump implements Shared_Interface
{
    public function __construct($shareName)
    {
        dump("INITIALIZING $shareName");
    }

    public function get($key, $defaultValue = NULL)
    {
        dump("Getting $key");
    }

    public function set($key, $value)
    {
        dump("Setting $key");
    }

    public function delete($key)
    {
        dump("Deleting $key");
    }

    public function destroy()
    {
        dump("Destroying");
    }
}