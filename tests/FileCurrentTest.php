<?php declare(strict_types = 1);

/**
 * Copyright (c) since 2004 Martin Takáč (http://martin.takac.name)
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace Taco\Nette\Forms\Controls;

use PHPUnit\Framework\TestCase;


class FileCurrentTest extends TestCase
{

	function testFreshFileWithDerivedName()
	{
		$inst = new FileCurrent("uploaded/account/56695/mp16.jpg", "image/jpeg");
		$this->assertStatus(
			$inst,
			name: 'mp16.jpg',
			path: 'uploaded/account/56695/mp16.jpg',
			contentType: 'image/jpeg',
			remove: False,
			filled: True
		);
	}



	function testFreshFileWithExplicitName()
	{
		$inst = new FileCurrent("uploaded/account/56695/mp16.jpg", "image/jpeg", "portrait.jpg");
		$this->assertStatus(
			$inst,
			name: 'portrait.jpg',
			path: 'uploaded/account/56695/mp16.jpg',
			contentType: 'image/jpeg',
			remove: False,
			filled: True
		);
	}



	function testEmptyNameFallsBackToBasename()
	{
		$inst = new FileCurrent("uploaded/account/56695/mp16.jpg", "image/jpeg", '');
		$this->assertStatus(
			$inst,
			name: 'mp16.jpg',
			path: 'uploaded/account/56695/mp16.jpg',
			contentType: 'image/jpeg',
			remove: False,
			filled: True
		);
	}



	function testRemovedFile()
	{
		$inst = new FileCurrent("uploaded/account/56695/mp16.jpg", "image/jpeg");
		$this->assertSame($inst, $inst->setRemove(), 'setRemove() is fluent');
		$this->assertStatus(
			$inst,
			name: 'mp16.jpg',
			path: 'uploaded/account/56695/mp16.jpg',
			contentType: 'image/jpeg',
			remove: True,
			filled: False
		);
	}



	function testExplicitlyKeptFile()
	{
		$inst = new FileCurrent("uploaded/account/56695/mp16.jpg", "image/jpeg");
		$inst->setRemove(False);
		$this->assertStatus(
			$inst,
			name: 'mp16.jpg',
			path: 'uploaded/account/56695/mp16.jpg',
			contentType: 'image/jpeg',
			remove: False,
			filled: True
		);
	}



	/**
	 * Verifies the complete status of the object - all methods at once.
	 */
	private function assertStatus(
		FileCurrent $inst,
		string $name,
		string $path,
		string $contentType,
		bool $remove,
		bool $filled
	): void
	{
		$this->assertSame($name, $inst->getName());
		$this->assertSame($path, $inst->getPath());
		$this->assertSame($path, $inst->getTemporaryFile());
		$this->assertSame($path, $inst->getId());
		$this->assertSame($contentType, $inst->getContentType());
		$this->assertSame($remove, $inst->isRemove());
		$this->assertSame($filled, $inst->isFilled());
		$this->assertSame(1, $inst->getSize());
		$this->assertSame(0, $inst->getError());
	}

}
