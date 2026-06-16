<?php declare(strict_types = 1);

/**
 * Copyright (c) since 2004 Martin Takáč (http://martin.takac.name)
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace Taco\Nette\Forms\Controls;

use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\Statement;
use Nette\Schema\Schema;
use Nette\Schema\Expect;
use Nette\PhpGenerator\ClassType;


class FileControlExtension extends CompilerExtension
{

	function getConfigSchema(): Schema
	{
		return Expect::structure([
			// Option to rename File::addFileControl().
			'name' => Expect::string()->default('FileControl'),

			// The ability to specify and set up your own transaction store.
			// Not required - default is UploadStoreTemp.
			'store' => Expect::type(Statement::class)->nullable(),
		]);
	}



	function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();
		$config = $this->getConfig();

		// We register the store as a service so that it can be injected into the controls.
		// Not autowired - it is referenced explicitly by name, and so it does not collide
		// with any other UploadStore service during autowiring.
		$builder->addDefinition($this->prefix('store'))
			->setType(UploadStore::class)
			->setFactory($config->store ?? new Statement(UploadStoreTemp::class))
			->setAutowired(false);
	}



	function afterCompile(ClassType $class): void
	{
		$config = $this->getConfig();
		$init = $class->getMethods()['initialize'];

		// Registers the addFileControl() / addMultiFileControl() extension methods
		// with the store taken from the DI container.
		$init->addBody(
			FileControl::class . '::register(?, $this->getService(?));',
			[$config->name, $this->prefix('store')]
		);
		$init->addBody(
			MultiFileControl::class . '::register(?, $this->getService(?));',
			[$config->name, $this->prefix('store')]
		);
	}

}
