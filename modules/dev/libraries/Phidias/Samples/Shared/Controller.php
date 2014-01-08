<?php
use Phidias\Core\Controller;
use Phidias\Component\Shared;

class Phidias_Samples_Shared_Controller extends Controller
{
    public function main()
    {
        echo "Hello there";

        $share  = new Shared('share_name');
        $var    = $share->get('var', 'nothing');

        dump("var is set to $var");

        $share->set('var', 'something');
    }
}