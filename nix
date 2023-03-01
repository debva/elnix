<?php

require_once(join(DIRECTORY_SEPARATOR, [getcwd(), 'vendor', 'autoload.php']));

use Debva\Elnix\Console;

$console = new Console;

$console->start();

exit;
