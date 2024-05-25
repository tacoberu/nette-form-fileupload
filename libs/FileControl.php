<?php
/**
 * Copyright (c) since 2004 Martin Takáč (http://martin.takac.name)
 * @license   https://opensource.org/licenses/MIT MIT
 */

namespace Taco\Nette\Forms\Controls;

use Nette\Forms\Form;
use Nette\Forms\Controls\UploadControl as NetteUploadControl;
use Nette\Forms\Controls\SubmitButton;
use Nette\Http\FileUpload;
use Nette\Utils\Html;
use Stringable;
use LogicException;


/**
 * If we have the file already uploaded, we can
 * - just want to display it (edit other details),
 * - or want to delete the file,
 * - or want to replace the file with another version.
 *
 * We load the files into the transaction. So in the case of an unrelated form error, we don't have to select the file and reload it.
 *
 * In the case of an unsuccessful error or a deleted file, value == Null.
 *
 * @author Martin Takáč <martin@takac.name>
 */
class FileControl extends NetteUploadControl
{

	/**
	 * A repository holding uploaded files before they are actually saved.
	 * By default it's just a temp directory, see UploadStoreTemp
	 *
	 * @var UploadStore
	 */
	private $store;

	/**
	 * @var ?FilePreviewer
	 */
	private $previewer = Null;

	/**
	 * @var Html
	 */
	private $container;

	/**
	 * @var Html  remove button template
	 */
	private $removeButton;

	/**
	 * @var Html  current file template
	 */
	private $currentControl;

	/**
	 * @var Html
	 */
	private $previewControl;

	/**
	 * @var Html
	 */
	private $transactionControl;


	function __construct(string|Stringable|null $label = null, UploadStore $store = Null)
	{
		parent::__construct($label, false);
		$this->setHtmlAttribute('data-taco-type', 'file');
		$this->store = (!empty($store)) ? $store : new UploadStoreTemp();
		$this->container = Html::el('div', [
			'class' => 'taco-file-control',
		]);
		$this->removeButton = Html::el('input', [
			'type' => 'submit',
			'value' => $this->translate('x'),
			'title' => $this->translate('Remove'),
			'formnovalidate' => '',
		]);
		$this->currentControl = Html::el('input', [
			'readonly' => 1,
			'style' => 'display: none',
		]);
		$this->previewControl = Html::el('input', [
			'readonly' => 1,
		]);
		$this->transactionControl = Html::el('input', [
			'type' => 'hidden',
		]);
	}



	/**
	 * By setting the previewer, uploaded files will be represented by their respective previews.
	 * @return self
	 */
	function setPreviewer(FilePreviewer $var)
	{
		$this->previewer = $var;
		return $this;
	}



	/**
	 * Loads HTTP data. File moved to transaction.
	 *
	 * @return void
	 */
	function loadHttpData(): void
	{
		// When I add a new Upload to the running request, the transaction number is missing
		$this->store->setId($this->getHttpData(Form::DataLine, '[transaction]'));

		if ($file = $this->getHttpData(Form::DataFile, '[new]')) {
			if ($file->isOk()) {
				$this->value = $this->store->append($file);
			}
			else {
				$this->addError($this->translate(Utils::formatError($file)));
				$this->value = Null;
			}
		}
		elseif ($rawvalue = $this->getHttpData(Form::DataText, '[current]')) {
			$value = Utils::createFileUploadedFromValue($rawvalue);
			// If it's in the store, it's not committed. How else would he get here?
			if ($this->store->exists($value->getId())) {
				$this->value = $value;
			}
			else {
				$this->value = Utils::createFileCurrentFromValue($rawvalue);
			}
		}
		else {
			$this->value = null;
		}

		if ($this->getHttpData(Form::DataLine, '[remove]')) {
			$this->value = null;
			$this->form->setSubmittedBy((new SubmitButton())->setValidationScope([]));
		}
	}



	/**
	 * Have been all files successfully uploaded?
	 */
	function isOk(): bool
	{
		return True;
	}



	/**
	 * Has been any file uploaded?
	 */
	function isFilled(): bool
	{
		if (empty($this->value)) {
			return False;
		}
		return $this->value->isFilled();
	}



	/**
	 * @return static
	 */
	function setValue($value)
	{
		if ($value instanceof FileCurrent) {
			$this->value = clone $value;
		}
		elseif ($value instanceof FileUploaded) {
			$this->value = clone $value;
		}
		elseif (empty($value)) {
			$this->value = Null;
		}
		else {
			throw new LogicException("Unexpected value.");
		}
		return $this;
	}



	/**
	 * All service inputs are unnamed. This will ensure that they are not sent to the server.
	 */
	function getControl()
	{
		switch (True) {
			// Existující soubor
			case $this->value instanceof FileCurrent:
			// Some file in the transaction.
			// The second round of the form
			case $this->value instanceof FileUploaded:
				$name = $this->getHtmlName();
				$container = clone $this->container;
				return $container
					->addHtml($this->getCurrentPart($name, $this->value))
					->addHtml($this->getPreviewControlPart($this->value))
					->addHtml($this->getRemoveButtonPart($name))
					->addHtml($this->getNewControlPart($name, withoutRequired: True))
					->addHtml($this->getTransactionControlPart($name));

			// No file selected
			// No default file
			// The first round of the form
			case empty($this->value):
				$name = $this->getHtmlName();
				$container = clone $this->container;
				return $container
					->addHtml($this->getNewControlPart($name, withoutRequired: False))
					->addHtml($this->getTransactionControlPart($name));

			default:
				throw new LogicException("oops");
		}
	}



	/**
	 * Returns container HTML element template.
	 */
	function getContainerPrototype(): Html
	{
		return $this->container;
	}



	/**
	 * Returns remove button HTML element template.
	 */
	function getRemoveButtonPrototype(): Html
	{
		return $this->removeButton;
	}



	function getRemoveButtonPart(string $name): Html
	{
		$el = clone $this->removeButton;
		$el->name = $name . '[remove]';
		return $el;
	}



	/**
	 * Returns current file HTML element template.
	 */
	function getCurrentControlPrototype(): Html
	{
		return $this->currentControl;
	}



	function getCurrentPart(string $name, FileUploaded|FileCurrent $value): Html
	{
		$el = clone $this->currentControl;
		$el->value = Utils::serializeFile($value);
		$el->name = $name . '[current]';
		return $el;
	}



	function getPreviewControlPrototype(): Html
	{
		return $this->previewControl;
	}



	function getPreviewControlPart(FileUploaded|FileCurrent $src): Html
	{
		if (empty($this->previewer)) {
			$el = clone $this->previewControl;
			$el->value = $src->getName();
			return $el;
		}
		return $this->previewer->getPreviewControlFor($src);
	}



	private function getNewControlPart(string $name, bool $withoutRequired): Html
	{
		$el = parent::getControl();
		if (!$el instanceof Html) {
			throw new LogicException("Expected only Html type.");
		}
		$el = clone $el;
		$el->name = $name . '[new]';
		// Existenci validujeme podle $name[current], ale nový záznam podle $name[new].
		if ($withoutRequired) {
			unset($el->required);
			$el->setAttribute('data-nette-rules', Utils::removeFilledRules($el->getAttribute('data-nette-rules')));
		}
		return $el;
	}



	private function getTransactionControlPart(string $name): Html
	{
		$el = clone $this->transactionControl;
		$el->name = $name . '[transaction]';
		$el->value = (string) $this->store->getId();
		return $el;
	}

}
