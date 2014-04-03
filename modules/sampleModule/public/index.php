<?php
exit("You must set the path fo Phidias Framework in " . __FILE__);

include '[path to Phidias Framework]/loader.php';

use Phidias\Environment;

Environment::start();