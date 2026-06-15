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

// Chceme i staré.
error_reporting(~E_USER_DEPRECATED);

// Specify folder for cache
umask(0);

// Autoloading tříd demo-aplikace (App\RouterFactory, presentery, …).
$configurator->createRobotLoader()
	->addDirectory(__DIR__)
	//~ ->addDirectory(__DIR__ . '/../libs')
	->register();

$configurator->addConfig(__DIR__ . '/configs/config.neon');
$configurator->addConfig(__DIR__ . '/configs/config.local.neon');

//~ Nette\Forms\Controls\BaseControl::enableAutoOptionalMode();

return $configurator->createContainer();
