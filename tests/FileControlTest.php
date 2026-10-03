<?php declare(strict_types = 1);

/**
 * Copyright (c) since 2004 Martin Takáč (http://martin.takac.name)
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace Taco\Nette\Forms\Controls;

use Nette\Forms\Form;
use Nette\Forms\Controls\SubmitButton;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;


class FileControlTest extends TestCase
{

	/**
	 * Regression: clicking "✕" drops the file and re-renders, but must not fire the
	 * form's onSuccess - that runs only on a real Save.
	 */
	function testRemoveDropsFileAndSuppressesFormEvents()
	{
		$control = $this->bindControl();
		$form = $control->getForm();
		$onSuccessCalled = False;
		$form->onSuccess[] = static function () use (&$onSuccessCalled): void {
			$onSuccessCalled = True;
		};

		$this->submit($control, [
			'transaction' => '123',
			'current' => Utils::serializeFile(new FileCurrent('uploaded/account/56695/mp16.jpg', 'image/jpeg', 0)),
			'remove' => 'X',
		]);

		// The file is dropped.
		$this->assertNull($control->getValue());

		// The form was submitted by a no-validation button with a non-empty onClick
		// (the non-empty onClick is what keeps Nette from warning about missing handlers).
		$button = $form->isSubmitted();
		$this->assertInstanceOf(SubmitButton::class, $button);
		$this->assertSame([], $button->getValidationScope());
		$this->assertNotEmpty($button->onClick);

		// Invoking the onClick (as fireEvents would, before onSuccess) clears the handlers.
		foreach ($button->onClick as $handler) {
			$handler($button);
		}
		$this->assertSame([], $form->onSuccess);
		$this->assertSame([], $form->onError);
		$this->assertSame([], $form->onSubmit);
		$this->assertFalse($onSuccessCalled);
	}



	/**
	 * Velikost části se řídí PHP (`upload_max_filesize` mínus rezerva); setMaxChunkSize() ji může
	 * jen snížit (typicky kvůli reverse proxy s menším limitem těla požadavku), nikdy zvýšit.
	 */
	function testMaxChunkSizeOnlyLowersTheLimitOfPhp()
	{
		$control = $this->bindControl();
		$resolve = new \ReflectionMethod($control, 'resolveChunkSize');
		$resolve->setAccessible(True);
		$auto = $resolve->invoke($control);
		$this->assertGreaterThan(0, $auto);

		$control->setMaxChunkSize(1000);
		$this->assertSame(1000, $resolve->invoke($control));

		$control->setMaxChunkSize($auto + 1000000);
		$this->assertSame($auto, $resolve->invoke($control));

		$control->setMaxChunkSize(Null);
		$this->assertSame($auto, $resolve->invoke($control));
	}



	function testMaxChunkSizeMustBePositive()
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->bindControl()->setMaxChunkSize(0);
	}



	private function bindControl(): FileControl
	{
		$store = new UploadStoreTemp('trx-', null, sys_get_temp_dir(), 0);
		$form = new Form();
		return $form['portrait'] = new FileControl('Portrait', $store);
	}



	/**
	 * @param array<string, mixed> $portrait
	 */
	private function submit(FileControl $control, array $portrait): void
	{
		$form = $control->getForm();
		$httpData = new ReflectionProperty(Form::class, 'httpData');
		$httpData->setAccessible(true);
		$httpData->setValue($form, ['portrait' => $portrait]);
		$submittedBy = new ReflectionProperty(Form::class, 'submittedBy');
		$submittedBy->setAccessible(true);
		$submittedBy->setValue($form, True);
		$control->loadHttpData();
	}

}
