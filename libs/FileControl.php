<?php declare(strict_types = 1);

/**
 * Copyright (c) since 2004 Martin Takáč (http://martin.takac.name)
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace Taco\Nette\Forms\Controls;

use Nette\Forms\Form;
use Nette\Forms\Controls\UploadControl as NetteUploadControl;
use Nette\Forms\Controls\SubmitButton;
use Nette\Forms\Container;
use Nette\Utils\Html;
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

	const RemoveButtonLabel = "✕"; // &#x2715;

	/**
	 * A repository holding uploaded files before they are actually saved.
	 * By default it's just a temp directory, see UploadStoreTemp
	 * @readonly
	 */
	private UploadStore $store;

	private ?FilePreviewer $previewer = Null;

	/**
	 * @readonly
	 */
	private Html $container;

	/**
	 * @var Html remove button template
	 * @readonly
	 */
	private Html $removeButton;

	/**
	 * @var Html current file template
	 * @readonly
	 */
	private Html $currentControl;

	/**
	 * @readonly
	 */
	private Html $previewControl;

	/**
	 * @readonly
	 */
	private Html $transactionControl;

	private string $prefix = "taco-filecontrol";

	/**
	 * Registers a form extension method `add{$name}` (default `addFileControl`),
	 * which creates a FileControl with the store injected from the DI container.
	 * The store can still be overridden by an explicit last argument.
	 */
	static function register(string $name = 'FileControl', ?UploadStore $store = Null): void
	{
		Container::extensionMethod('add' . $name, static function (
			Container $container,
			string $controlName,
			$label = Null,
			?UploadStore $localStore = Null
		) use ($store): self {
			$control = new self($label, $localStore ?: $store);
			$container->addComponent($control, $controlName);
			return $control;
		});
	}



	/**
	 * @param string|null $label
	 */
	function __construct($label = null, ?UploadStore $store = Null)
	{
		parent::__construct($label, false);

		$this->setHtmlAttribute('data-taco-type', 'file');
		$this->store = $store instanceof UploadStore
			? $store
			: new UploadStoreTemp();
		$this->container = Html::el('div', [
			'class' => $this->formatClass(Null),
		]);
		$this->removeButton = Html::el('input', [
			'type' => 'submit',
			'value' => $this->translate(self::RemoveButtonLabel),
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
	 */
	function setPreviewer(FilePreviewer $var): self
	{
		$this->previewer = $var;
		return $this;
	}



	/**
	 * Loads HTTP data. File moved to transaction.
	 */
	function loadHttpData(): void
	{
		// When I add a new Upload to the running request, the transaction number is missing
		$id = $this->getHttpData(Form::DataLine, '[transaction]');
		$this->store->setId($id ? (int) $id : Null);

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
			$this->value = $this->store->exists($value->getId())
				? $value
				: Utils::createFileCurrentFromValue($rawvalue);
		}
		else {
			$this->value = null;
		}

		// No-JS fallback: the "✕" button submits the whole form. We drop the file (above),
		// but suppress the form's submit handlers so onSuccess fires only on a real Save.
		// The clearing must happen inside onClick (runs before onSuccess), otherwise the
		// form has "no associated handlers" and Nette warns. See MultiFileControl::loadHttpData().
		if ($this->getHttpData(Form::DataLine, '[remove]')) {
			$this->value = null;
			$form = $this->getForm();
			$button = new SubmitButton();
			$button->setValidationScope([]);
			$button->onClick[] = static function () use ($form): void {
				$form->onSuccess = $form->onError = $form->onSubmit = [];
			};
			$form->setSubmittedBy($button);
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
	 * Explicitní vymazání transakce.
	 */
	function destroyStore(): void
	{
		$this->store->destroy();
	}



	function setValue($value): self
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
					->addHtml($this->getNewControlPart($name, True))
					->addHtml($this->getTransactionControlPart($name));

			// No file selected
			// No default file
			// The first round of the form
			case empty($this->value):
				$name = $this->getHtmlName();
				$container = clone $this->container;
				return $container
					->addHtml($this->getNewControlPart($name, False))
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



	/**
	 * @param FileUploaded | FileCurrent $value
	 */
	function getCurrentPart(string $name, $value): Html
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



	/**
	 * @param FileUploaded | FileCurrent $src
	 */
	function getPreviewControlPart($src): Html
	{
		if (!$this->previewer instanceof FilePreviewer) {
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



	private function formatClass(?string $suffix): string
	{
		return $suffix
			? "{$this->prefix}-{$suffix}"
			: $this->prefix;
	}

}
