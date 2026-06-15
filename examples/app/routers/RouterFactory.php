<?php declare(strict_types = 1);

/**
 * Copyright (c) since 2004 Martin Takáč (http://martin.takac.name)
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace App;

use Nette;
use Nette\Application\Routers\RouteList;
use Nette\Application\Routers\Route;


/**
 * Router factory.
 */
class RouterFactory
{

	/**
	 * @return Nette\Application\IRouter
	 */
	static function createRouter()
	{
		$router = new RouteList();
		$router[] = new Route('index.php', 'Dashboard:default', Route::ONE_WAY);
		$router[] = new Route('<presenter>/<action>[/<id>]', 'Dashboard:default');
		return $router;
	}

}
