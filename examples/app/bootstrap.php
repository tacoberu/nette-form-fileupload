<?php declare(strict_types = 1);

/**
 * Copyright (c) since 2004 Martin Takáč (http://martin.takac.name)
 * @license https://opensource.org/licenses/MIT MIT
 */

use Nette\Configurator;


require __DIR__ . '/../../vendor/autoload.php';

$configurator = new Configurator();

//$configurator->setDebugMode(TRUE);  // debug mode MUST NOT be enabled on production server
$configurator->enableDebugger(__DIR__ . '/../../var/logs');
$configurator->setTempDirectory(__DIR__ . '/../../temp');

// Include deprecated notices.
error_reporting(~E_USER_DEPRECATED);

// Specify folder for cache
umask(0);

// Autoload demo application classes (App\RouterFactory, presenters, …).
$configurator->createRobotLoader()
	->addDirectory(__DIR__)
	//~ ->addDirectory(__DIR__ . '/../libs')
	->register();

$configurator->addConfig(__DIR__ . '/configs/config.neon');
$configurator->addConfig(__DIR__ . '/configs/config.local.neon');

//~ Nette\Forms\Controls\BaseControl::enableAutoOptionalMode();

return $configurator->createContainer();
