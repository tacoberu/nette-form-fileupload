<?php declare(strict_types = 1);

/**
 * Copyright (c) since 2004 Martin Takáč (http://martin.takac.name)
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace Taco\Nette\Forms\Controls;

use Nette\Forms\Form;
use Nette\Http\FileUpload;
use PHPUnit\Framework\TestCase;
use LogicException;


class UtilsTest extends TestCase
{

	function testSerializeFileCurrent()
	{
		$src = new FileCurrent("tasks/6s3qva8l/4728-05.jpg", "image/jpeg");
		$this->assertSame('image/jpeg#tasks/6s3qva8l/4728-05.jpg', Utils::serializeFile($src));
	}



	function testSerializeFileUploaded()
	{
		$src = new FileUploaded("tasks/6s3qva8l/4728-05.jpg", "image/jpeg");
		$this->assertSame('image/jpeg#tasks/6s3qva8l/4728-05.jpg', Utils::serializeFile($src));
	}



	function testCreateFileUploadedFromValue()
	{
		$inst = Utils::createFileUploadedFromValue('image/jpeg#tasks/6s3qva8l/4728-05.jpg');
		$this->assertInstanceOf(FileUploaded::class, $inst);
		$this->assertSame('tasks/6s3qva8l/4728-05.jpg', $inst->getId());
		$this->assertSame('image/jpeg', $inst->getContentType());
	}



	function testCreateFileCurrentFromValue()
	{
		$inst = Utils::createFileCurrentFromValue('image/jpeg#tasks/6s3qva8l/4728-05.jpg');
		$this->assertInstanceOf(FileCurrent::class, $inst);
		$this->assertSame('tasks/6s3qva8l/4728-05.jpg', $inst->getId());
		$this->assertSame('image/jpeg', $inst->getContentType());
	}



	function testSerializeAndCreateRoundtrip()
	{
		$value = 'image/jpeg#tasks/6s3qva8l/4728-05.jpg';
		$this->assertSame($value, Utils::serializeFile(Utils::createFileUploadedFromValue($value)));
		$this->assertSame($value, Utils::serializeFile(Utils::createFileCurrentFromValue($value)));
	}



	function testPathContainingHashIsPreserved()
	{
		// Only the first '#' separates the type, the path may contain more.
		$inst = Utils::createFileUploadedFromValue('image/jpeg#tasks/a#b/4728.jpg');
		$this->assertSame('tasks/a#b/4728.jpg', $inst->getId());
		$this->assertSame('image/jpeg', $inst->getContentType());
		$this->assertSame('image/jpeg#tasks/a#b/4728.jpg', Utils::serializeFile($inst));
	}



	function testRemoveFilledRulesDropsTopLevelFilled()
	{
		$rules = [
			['op' => Form::Filled],
			['op' => Form::Email],
			['op' => Form::MaxLength],
		];
		$this->assertSame([
			['op' => Form::Email],
			['op' => Form::MaxLength],
		], Utils::removeFilledRules($rules));
	}



	function testRemoveFilledRulesRecursesIntoNestedRules()
	{
		$rules = [
			['op' => Form::Equal, 'rules' => [
				['op' => Form::Filled],
				['op' => Form::MinLength],
			]],
		];
		$this->assertSame([
			['op' => Form::Equal, 'rules' => [
				['op' => Form::MinLength],
			]],
		], Utils::removeFilledRules($rules));
	}



	function testRemoveFilledRulesKeepsRulesWithoutFilled()
	{
		$rules = [
			['op' => Form::Email],
			['op' => Form::MaxLength],
		];
		$this->assertSame($rules, Utils::removeFilledRules($rules));
	}



	function testRemoveFilledRulesOnEmptyArray()
	{
		$this->assertSame([], Utils::removeFilledRules([]));
	}



	function testFormatErrorKnownError()
	{
		$file = $this->upload(UPLOAD_ERR_INI_SIZE);
		$this->assertSame(
			'foo.jpg: The uploaded file exceeds the upload_max_filesize directive in php.ini',
			Utils::formatError($file)
		);
	}



	function testFormatErrorNoFile()
	{
		$file = $this->upload(UPLOAD_ERR_NO_FILE);
		$this->assertSame('foo.jpg: No file was uploaded', Utils::formatError($file));
	}



	function testFormatErrorUnknownCode()
	{
		$file = $this->upload(999);
		$this->assertSame('foo.jpg: Unknown upload error', Utils::formatError($file));
	}



	function testFormatErrorThrowsOnNoError()
	{
		$file = $this->upload(UPLOAD_ERR_OK);
		$this->expectException(LogicException::class);
		Utils::formatError($file);
	}



	private function upload(int $error, string $name = 'foo.jpg'): FileUpload
	{
		return new FileUpload([
			'name' => $name,
			'size' => 0,
			'tmp_name' => '',
			'error' => $error,
		]);
	}

}
