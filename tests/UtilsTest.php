<?php declare(strict_types = 1);

/**
 * Copyright (c) since 2004 Martin Takáč (http://martin.takac.name)
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace Taco\Nette\Forms\Controls;

use Nette\Forms\Form;
use Nette\Http\FileUpload;
use Nette\Utils\Json;
use PHPUnit\Framework\TestCase;
use LogicException;


class UtilsTest extends TestCase
{

	function testSerializeFileCurrent()
	{
		$src = new FileCurrent("tasks/6s3qva8l/4728-05.jpg", "image/jpeg", 42);
		$this->assertSame(
			Json::encode(['c', 'image/jpeg', 42, 'tasks/6s3qva8l/4728-05.jpg', '4728-05.jpg']),
			Utils::serializeFile($src)
		);
	}



	function testSerializeFileUploaded()
	{
		$src = new FileUploaded("tasks/6s3qva8l/4728-05.jpg", "image/jpeg", 42);
		$this->assertSame(
			Json::encode(['u', 'image/jpeg', 42, 'tasks/6s3qva8l/4728-05.jpg', '4728-05.jpg']),
			Utils::serializeFile($src)
		);
	}



	function testCreateFileValueFromRaw()
	{
		$src = new FileCurrent("tasks/6s3qva8l/4728-05.jpg", "image/jpeg", 42);
		$result = Utils::createFileValueFromRaw(Utils::serializeFile($src));
		$this->assertInstanceOf(FileCurrent::class, $result);
		$this->assertSame('tasks/6s3qva8l/4728-05.jpg', $result->getId());
		$this->assertSame('image/jpeg', $result->getContentType());
		$this->assertSame(42, $result->getSize());

		$src2 = new FileUploaded("tasks/6s3qva8l/4728-05.jpg", "image/jpeg", 42);
		$result2 = Utils::createFileValueFromRaw(Utils::serializeFile($src2));
		$this->assertInstanceOf(FileUploaded::class, $result2);
		$this->assertSame('tasks/6s3qva8l/4728-05.jpg', $result2->getId());
		$this->assertSame('image/jpeg', $result2->getContentType());
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
		$limit = \Nette\Forms\Helpers::iniGetSize('upload_max_filesize');
		$this->assertSame(
			'foo.jpg: ' . sprintf(\Nette\Forms\Validator::$messages[Form::MaxFileSize], $limit),
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
