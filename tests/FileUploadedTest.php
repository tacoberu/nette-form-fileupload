<?php declare(strict_types = 1);

/**
 * Copyright (c) since 2004 Martin Takáč (http://martin.takac.name)
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace Taco\Nette\Forms\Controls;

use PHPUnit\Framework\TestCase;


class FileUploadedTest extends TestCase
{

	function testFreshFileWithDerivedName()
	{
		$inst = new FileUploaded("/tmp/upload-669965256695/mp16.jpg", "image/jpeg", 0);
		$this->assertSame('mp16.jpg', $inst->getName());
		$this->assertSame('/tmp/upload-669965256695/mp16.jpg', $inst->getId());
		$this->assertSame('image/jpeg', $inst->getContentType());
		$this->assertSame(0, $inst->getSize());
	}



	function testFreshFileWithExplicitName()
	{
		$inst = new FileUploaded("/tmp/upload-669965256695/mp16.jpg", "image/jpeg", 0, "portrait.jpg");
		$this->assertSame('portrait.jpg', $inst->getName());
		$this->assertSame('/tmp/upload-669965256695/mp16.jpg', $inst->getId());
		$this->assertSame('image/jpeg', $inst->getContentType());
	}



	function testEmptyNameFallsBackToBasename()
	{
		$inst = new FileUploaded("/tmp/upload-669965256695/mp16.jpg", "image/jpeg", 0, '');
		$this->assertSame('mp16.jpg', $inst->getName());
	}



	function testSizeIsStored()
	{
		$inst = new FileUploaded("/tmp/upload-669965256695/mp16.jpg", "image/jpeg", 42);
		$this->assertSame(42, $inst->getSize());
	}

}
