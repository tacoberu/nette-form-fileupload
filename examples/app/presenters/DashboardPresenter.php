<?php declare(strict_types = 1);

/**
 * Copyright (c) since 2004 Martin Takáč (http://martin.takac.name)
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace App\Presenters;

use Nette\Application\UI\Presenter as BasePresenter;
use Nette\Application\UI\Form;
use Taco\Nette\Forms\Controls\FileCurrent;
use Taco\Nette\Forms\Controls\GenericFilePreviewer;
use Taco\Nette\Forms\Controls\UploadStoreTemp;
use Taco\Nette\Forms\Controls\FileUploadFactory;


class DashboardPresenter extends BasePresenter
{

	private const ImageFile = __DIR__ . '/../../document_root/img/the-cat.jpeg';

	/**
	 * @var FileUploadFactory @inject
	 */
	public $fileUploadFactory; // @phpcs:ignore SlevomatCodingStandard.Classes.ForbiddenPublicProperty.ForbiddenPublicProperty

	function renderFile()
	{
		$this['fileForm']->setDefaults([
			'title' => 'Abc',
			'content' => 'Lorem ipsum doler ist.',
			'portrait1' => new FileCurrent("uploaded/account/56695/mp16.jpg", "image/jpeg", 42, "Moje cosi"),
			'portrait2' => new FileCurrent("uploaded/account/56695/mp16.jpg", "image/jpeg", 42, "Moje cosi"),
			//~ 'portrait5' => new FileCurrent(self::ImageFile, "image/jpeg"),
		]);
	}



	function renderFiles()
	{
		$this['filesForm']->setDefaults([
			'title' => 'Abc',
			'content' => 'Lorem ipsum doler ist.',
			'attachments2' => [
				new FileCurrent("uploaded/account/56695/mp16.jpg", "image/jpeg", 42, "Moje cosi"),
				//~ new FileCurrent(self::ImageFile, "image/jpeg"),
			],
		]);
	}



	protected function createComponentBioForm()
	{
		$form = $this->makeForm();

		$form->addText('title', 'Title a:')
			->setRequired('Please enter a title.');
		$form->addTextarea('content', 'Content:')
			->setRequired('Please enter a content.');

		$form->addFileControl('portrait', 'Portrait')
			->setPreviewer(new GenericFilePreviewer(__dir__ . '/../../data'))
			->setRequired('Please enter a content.');

		$form->setCurrentGroup(NULL);
		$form->addSubmit('submit', 'Save')
			->setAttribute('class', 'default');

		$form->addSubmit('cancel', 'Cancel')
			->setValidationScope([]);

		$form->onSuccess[] = static function($form, $values) {
			dump($values);
			//~ $form['portrait']->destroyStore();
			//~ $form['portrait2']->destroyStore();
			//~ $form['portrait3']->destroyStore();
			//~ $form['portrait4']->destroyStore();
			//~ $form['portrait5']->destroyStore();
			//~ $form['portrait6']->destroyStore();
			//~ $form['portrait7']->destroyStore();
			//~ die("\n------\n" . __file__ . ':' . __line__ . "\n");
		};

		return $form;
	}



	protected function createComponentFileForm()
	{
		$form = $this->makeForm();

		$previewer = new GenericFilePreviewer(__dir__ . '/../../data');
		$store = new UploadStoreTemp('uploading/trx-', baseDir: __dir__ . '/../../../temp');

		$form->addText('title', 'Title a:')
			->setRequired('Please enter a title.');
		$form->addTextarea('content', 'Content:')
			->setRequired('Please enter a content.');

		$form->addFileControl('portrait1', 'Portrait')
			->setPreviewer($previewer);
		$form->addFileControl('portrait2', 'Portrait 2', $store);
		$form['portrait2']->getRemoveButtonPrototype()
			->setValue('Smazat');
		$form->addFileControl('portrait3','Portrait 3')
			->setPreviewer($previewer);
		$form['portrait4'] = $this->fileUploadFactory->addUploadControl('Portrait 4');
		$form['portrait5'] = $this->fileUploadFactory
			->addUploadControl('Portrait 5')
			->setPreviewer($previewer);
		$form['portrait5']
			->getRemoveButtonPrototype()
			->setValue('Smazat')
			->setTitle('Smazat');
		$form->addFileControl('portrait6', 'Portrait 6')
			->setOption("description", "Required field")
			->setRequired()
			->addRule($form::MaxFileSize, "File size must not exceed %d bytes.", 255);

		$form->addCheckbox("aux", "Attachment?");

		$form->addFileControl('portrait7', 'Portrait 7')
			->setOption("description", "Required when attachment is checked.");
		$form['portrait7']
			->addConditionOn($form['aux'], $form::Equal, true)
				->setRequired("Required: %name");

		$form->setCurrentGroup(NULL);
		$form->addSubmit('submit', 'Save')
			->setAttribute('class', 'default');

		$form->addSubmit('cancel', 'Cancel')
			->setValidationScope([]);

		$form->onSuccess[] = static function($form, $values) {
			dump($values);
			//~ $form['portrait']->destroyStore();
			//~ $form['portrait2']->destroyStore();
			//~ $form['portrait3']->destroyStore();
			//~ $form['portrait4']->destroyStore();
			//~ $form['portrait5']->destroyStore();
			//~ $form['portrait6']->destroyStore();
			//~ $form['portrait7']->destroyStore();
			//~ die("\n------\n" . __file__ . ':' . __line__ . "\n");
		};

		return $form;
	}



	/**
	 * @return Form
	 */
	protected function createComponentFilesForm()
	{
		$form = $this->makeForm();
		$previewer = new GenericFilePreviewer(__dir__ . '/../../data');

		$form->addText('title', 'Title:')
			->setRequired('Please enter a title.');
		$form->addTextarea('content', 'Content:')
			->setRequired('Please enter a content.');

		$form->addMultiFileControl('attachments1', 'Attachments 1')
			->setPreviewer($previewer);

		$form->addMultiFileControl('attachments2', 'Attachments 2');

		$form->setCurrentGroup(NULL);
		$form->addSubmit('submit', 'Save')
			->setAttribute('class', 'default');

		$form->addSubmit('cancel', 'Cancel')
			->setValidationScope(array());

		$form->onSuccess[] = static function($form, $values) {
			//~ dump($form->getValues());
			dump($values);
		};

		return $form;
	}



	private function makeForm($initcb = Null)
	{
		$form = new Form();
		$form->addProtection();
		$form->onSuccess[] = $this->createProcessSubmitted();
		$form->onAnchor[] = static function($form) {
			if ( ! $form->isSubmitted()) {
				// Initialize default values here if needed
			}
		};

		return $form;
	}



	private function createProcessSubmitted()
	{
		return function (Form $form) {

			if ($this->presenter->isAjax()) {
				$this->presenter->redrawControl('task');
				//~ $form->redrawControl();
				//~ dump($form->getName());
				$this->presenter->redrawControl($form->getName());
				//~ die('=====[' . __line__ . '] ' . __file__);
			}

			if (isset($form['cancel']) && $form['cancel']->isSubmittedBy()) {
				$this->presenter->redirect('Dashboard:');
			}
/*
			if (isset($form['submit']) && $form['submit']->isSubmittedBy()) {

				// Reformat
				$values = $form->getValues();
				//~ dump($values);
				if (isset($form['attachments'])) {
					//~ $form['attachments']->destroy();
				}
			}
*/
		};
	}

}
