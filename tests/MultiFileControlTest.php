<?php declare(strict_types = 1);

/**
 * Copyright (c) since 2004 Martin Takáč (http://martin.takac.name)
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace Taco\Nette\Forms\Controls;

use Nette\Forms\Form;
use PHPUnit\Framework\TestCase;
use FilesystemIterator;
use ReflectionProperty;


class MultiFileControlTest extends TestCase
{

	private string $baseDir;

	/**
	 * Regression: the "use" checkbox must carry name + value on the <input> itself,
	 * otherwise it is not submitted and existing files are lost on a re-submit.
	 */
	function testUseCheckboxIsSubmittable()
	{
		$control = $this->bindControl();
		$file = new FileCurrent('/uploaded/cat.jpeg', 'image/jpeg', 0);
		$control->setValue([$file]);

		$html = (string) $control->getControl();

		$this->assertMatchesRegularExpression('~<input[^>]*type="checkbox"[^>]*name="attachments\[use\]\[\]"~', $html);
		$serialized = Utils::serializeFile($file);
		$this->assertStringContainsString($serialized, $html);
	}



	/**
	 * Regression: a re-submit without new uploads (files arrive via current[] + use[])
	 * must keep the already uploaded files.
	 */
	function testResubmitKeepsCheckedFiles()
	{
		$control = $this->bindControl();
		$file = new FileCurrent($this->baseDir . '/uploading/txt-123/cat.jpeg', 'image/jpeg', 0);
		$serialized = Utils::serializeFile($file);
		$this->submit($control, [
			'transaction' => '123',
			'current' => [$serialized],
			'use' => [$serialized],
		]);

		$this->assertCount(1, $control->getValue());
	}



	/**
	 * Unchecking a file (its value is missing from use[]) removes it - that is intentional.
	 */
	function testUncheckedFileIsRemoved()
	{
		$control = $this->bindControl();
		$file = new FileCurrent($this->baseDir . '/uploading/txt-123/cat.jpeg', 'image/jpeg', 0);
		$serialized = Utils::serializeFile($file);
		$this->submit($control, [
			'transaction' => '123',
			'current' => [$serialized],
			'use' => [], // nothing checked
		]);

		$this->assertCount(0, $control->getValue());
	}



	protected function setUp(): void
	{
		$this->baseDir = sys_get_temp_dir() . '/multifile-test-' . uniqid();
		mkdir($this->baseDir);
	}



	protected function tearDown(): void
	{
		$this->removeRecursively($this->baseDir);
	}



	private function bindControl(): MultiFileControl
	{
		$store = new UploadStoreTemp('uploading/txt-', null, $this->baseDir, 0);
		$form = new Form();
		return $form['attachments'] = new MultiFileControl('Attachments', $store);
	}



	/**
	 * @param array<string, mixed> $attachments
	 */
	private function submit(MultiFileControl $control, array $attachments): void
	{
		$form = $control->getForm();
		$httpData = new ReflectionProperty(Form::class, 'httpData');
		$httpData->setAccessible(true);
		$httpData->setValue($form, ['attachments' => $attachments]);
		$submittedBy = new ReflectionProperty(Form::class, 'submittedBy');
		$submittedBy->setAccessible(true);
		$submittedBy->setValue($form, True);
		$control->loadHttpData();
	}



	private function removeRecursively(string $dir): void
	{
		if (!is_dir($dir)) {
			return;
		}
		foreach (new FilesystemIterator($dir) as $item) {
			if ($item->isDir()) {
				$this->removeRecursively((string) $item);
			}
			else {
				unlink((string) $item);
			}
		}
		rmdir($dir);
	}

}
