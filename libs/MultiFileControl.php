<?php declare(strict_types = 1);

/**
 * Copyright (c) since 2004 Martin Takáč (http://martin.takac.name)
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace Taco\Nette\Forms\Controls;

use Nette;
use Nette\Utils\Html;
use Nette\Forms\Form;
use Nette\Forms\Container;
use Nette\Forms\Controls\SubmitButton;
use Nette\Forms\Controls\UploadControl as NetteUploadControl;
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
class MultiFileControl extends NetteUploadControl
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
	 * @var Html
	 */
	private $itemControl;

	/**
	 * @var Html
	 */
	private $useCheckbox;

	/**
	 * @var Html current file template
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

	/**
	 * @var Html
	 */
	private $preloadButton;

	/**
	 * Registers a form extension method `addMulti{$name}` (default `addMultiFileControl`),
	 * which creates a MultiFileControl with the store injected from the DI container.
	 * The store can still be overridden by an explicit last argument.
	 */
	static function register(string $name = 'FileControl', ?UploadStore $store = Null): void
	{
		Container::extensionMethod('addMulti' . $name, static function (
			Container $container,
			string $controlName,
			string|Stringable|null $label = Null,
			?UploadStore $localStore = Null
		) use ($store): self {
			$control = new self($label, $localStore ?: $store);
			$container->addComponent($control, $controlName);
			return $control;
		});
	}



	function __construct(string|Stringable|null $label = null, ?UploadStore $store = Null)
	{
		parent::__construct($label);

		$this->container = Html::el('div', [
			'data-taco-type' => 'file',
			'class' => 'taco-file-control taco-multifile-control',
			//~ 'style' => 'border: 1px solid red',
		]);
		$this->itemControl = Html::el('div', [
			//~ 'style' => 'border: 1px solid green',
		]);
		$this->useCheckbox = Html::el('input', [
			'type' => 'checkbox',
			'checked' => True,
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
		$this->preloadButton = Html::el('input', [
			'type' => 'submit',
			'value' => $this->translate('Preload'),
			'formnovalidate' => '',
		]);

		$this->store = !empty($store)
			? $store
			: new UploadStoreTemp();

		$this->monitor(Form::class, static function (Form $form): void {
			if ( ! $form->isMethod('post')) {
				throw new Nette\InvalidStateException('File upload requires method POST.');
			}
			$form->getElementPrototype()->enctype = 'multipart/form-data';
		});
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
	 * Set control's values.
	 *
	 * @param array<mixed> $values
	 */
	function setValue($values)
	{
		$this->value = [];
		if ($values && is_array($values)) {
			foreach ($values as $x) {
				self::assertFileValue($x);
			}
			$this->value = $values;
		}
		return $this;
	}



	/**
	 * Returning values.
	 * @return array<FileUploaded|FileCurrent>
	 */
	function getValue()
	{
		return (array) $this->value;
	}



	/**
	 * Loads HTTP data. Files moved to transaction.
	 */
	function loadHttpData(): void
	{
		// When I add a new Upload to the running request, the transaction number is missing
		$this->store->setId($this->getHttpData(Form::DataLine, '[transaction]'));

		// Odškrtnutí znamená vyhodit.
		$used = $this->getHttpData(Form::DataLine, '[use][]');

		$values = [];
		if ($rawvalues = $this->getHttpData(Form::DataText, '[current][]')) {
			foreach ($rawvalues as $rawvalue) {
				if (!in_array($rawvalue, $used, True)) {
					continue;
				}
				$value = Utils::createFileUploadedFromValue($rawvalue);
				// If it's in the store, it's not committed. How else would he get here?
				$values[] = $this->store->exists($value->getId())
					? $value
					: Utils::createFileCurrentFromValue($rawvalue);
			}
		}

		// Ty, co přišli v pořádku, tak uložit do transakce, co nejsou v pořádku zahodit a oznámit neuspěch.
		if ($files = $this->getHttpData(Form::DataFile, '[new][]')) {
			foreach ($files as $file) {
				if ($file->isOk()) {
					$values[] = $this->store->append($file);
				}
				else {
					$this->addError($this->translate(Utils::formatError($file)));
				}
			}
		}

		$this->value = $values;

		if ($this->getHttpData(Form::DataLine, '[preload]')) {
			$this->form->setSubmittedBy((new SubmitButton())->setValidationScope([]));
		}
	}



	/**
	 * Html representation of control.
	 *
	 * @return Html
	 */
	function getControl()
	{
		$name = $this->getHtmlName();
		$container = clone $this->container;
		foreach ($this->value as $item) {
			$container->addHtml($this->getItemControlPart($name, $item));
		}
		$container->addHtml($this->getItemControlPart($name, Null));
		$container->addHtml($this->getPreloadButtonPart($name));
		$container->addHtml($this->getTransactionControlPart($name));

		return $container;
	}



	function getCurrentPart(string $name, FileUploaded|FileCurrent $value): Html
	{
		$el = clone $this->currentControl;
		$el->value = Utils::serializeFile($value);
		$el->name = $name . '[current][]';
		return $el;
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



	function getUseCheckboxPart(string $name, FileUploaded|FileCurrent $src): Html
	{
		$el = clone $this->useCheckbox;
		$el->name = $name . '[use][]';
		$el->value = Utils::serializeFile($src);
		return $el;
	}



	function getPreloadButtonPart(string $name): Html
	{
		$el = clone $this->preloadButton;
		$el->name = $name . '[preload]';
		return $el;
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
		return True;
	}



	/**
	 * Odstranění adresáře s transakcí.
	 */
	function destroyStore(): void
	{
		$this->store->destroy();
	}



	private function getItemControlPart(string $name, FileUploaded|FileCurrent|Null $value): Html
	{
		$el = clone $this->itemControl;
		if (empty($value)) {
			$el->addHtml($this->getNewControlPart($name, withoutRequired: False));
		}
		else {
			$el->addHtml($this->getUseCheckboxPart($name, $value));
			$el->addHtml($this->getCurrentPart($name, $value));
			$el->addHtml($this->getPreviewControlPart($value));
		}
		return $el;
	}



	private function getNewControlPart(string $name, bool $withoutRequired): Html
	{
		$el = parent::getControl();
		if (!$el instanceof Html) {
			throw new LogicException("Expected only Html type.");
		}
		$el = clone $el;
		$el->name = $name . '[new][]';
		$el->multiple = True;
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



	private static function assertFileValue(FileCurrent $m): void
	{
		// The parameter type-hint already guarantees the value;
		// kept as an extension point for stricter checks.
	}

}
