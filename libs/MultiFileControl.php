<?php declare(strict_types = 1);

/**
 * Copyright (c) since 2004 Martin Takáč (http://martin.takac.name)
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace Taco\Nette\Forms\Controls;

use Nette\Utils\Html;
use Nette\Forms\Form;
use Nette\Forms\Container;
use Nette\Forms\Controls\BaseControl;
use Nette\Forms\Controls\UploadControl as NetteUploadControl;
use Nette\Forms\Controls\SubmitButton;
use Nette\Forms;
use Nette\Application\UI\Presenter;
use Nette\Application\UI\SignalReceiver;
use Nette\InvalidStateException;
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
class MultiFileControl extends BaseControl implements SignalReceiver
{

	use FileControlUnit;

	/**
	 * @readonly
	 */
	private Html $container;

	/**
	 * @readonly
	 */
	private Html $itemControl;

	/**
	 * @readonly
	 */
	private Html $useCheckbox;

	/**
	 * @var Html current file template
	 * @readonly
	 */
	private Html $currentControl;

	/**
	 * @readonly
	 */
	private Html $labelControl;

	/**
	 * @readonly
	 */
	private Html $transactionControl;

	/**
	 * @readonly
	 */
	private Html $preloadButton;

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
		parent::__construct($label);

		// File upload setup — replaces what UploadControl's constructor did.
		$this->control->type = 'file';
		$this->setOption('type', 'file');
		$this->addCondition(true)
			->addRule([$this, 'isOk'], Forms\Validator::$messages[NetteUploadControl::Valid]);
		$this->monitor(Form::class, static function (Form $form): void {
			if ( ! $form->isMethod('post')) {
				throw new InvalidStateException('File upload requires method POST.');
			}
			$form->getElementPrototype()->enctype = 'multipart/form-data';
			Utils::checkPostMaxSize($form);
		});

		$this->container = Html::el('div', [
			'data-taco-type' => 'file multiple',
			'class' => $this->formatClass(Null) . ' ' . $this->formatClass('multiple'),
		]);
		$this->itemControl = Html::el('div', [
			'class' => $this->formatClass('row'),
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
		$this->labelControl = Html::el('input', [
			'readonly' => 1,
		]);
		$this->transactionControl = Html::el('input', [
			'type' => 'hidden',
		]);
		$this->preloadButton = Html::el('input', [
			'type' => 'submit',
			'value' => $this->translate('↻'),
			'class' => $this->formatClass('preload'),
			'title' => $this->translate('Preload'),
			'formnovalidate' => '',
		]);

		$this->store = $store instanceof UploadStore
			? $store
			: new UploadStoreTemp();
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
	function getValue(): array
	{
		return (array) $this->value;
	}



	/**
	 * Loads HTTP data. Files moved to transaction.
	 */
	function loadHttpData(): void
	{
		// When I add a new Upload to the running request, the transaction number is missing
		$id = $this->getHttpData(Form::DataLine, '[transaction]');
		$this->store->setId($id ? (int) $id : Null);

		// Unchecked items are discarded.
		$used = $this->getHttpData(Form::DataLine, '[use][]');
		$used = array_unique($used);

		$values = [];
		if ($rawvalues = $this->getHttpData(Form::DataText, '[current][]')) {
			$rawvalues = array_unique($rawvalues);
			foreach ($rawvalues as $rawvalue) {
				if (!in_array($rawvalue, $used, True)) {
					continue;
				}
				$value = Utils::createFileValueFromRaw($rawvalue);
				$values[] = $value;
			}
		}

		// Move successfully uploaded files into the transaction; report errors for the rest.
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

		// No-JS fallback: the "↻" button submits the whole form. We process the upload
		// (above), but suppress the form's submit handlers so onSuccess fires only on Save.
		// Inspired by Contributte Multiplier's resetFormEvents(). With JS this path is not
		// used - the button is intercepted and posted to the handlePreload() signal instead.
		if ($this->getHttpData(Form::DataLine, '[preload]')) {
			$form = $this->getForm();
			$button = new SubmitButton();
			$button->setValidationScope([]);
			// The clearing must happen inside onClick (runs before onSuccess), otherwise
			// the form has "no associated handlers" and Nette warns.
			$button->onClick[] = static function () use ($form): void {
				$form->onSuccess = $form->onError = $form->onSubmit = [];
			};
			$form->setSubmittedBy($button);
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
		if ($this->lookup(Presenter::class, false) !== null) {
			$container->setAttribute('data-upload-url', $this->link(':upload!'));
			$chunkSize = Forms\Helpers::iniGetSize('upload_max_filesize') - 100 * 1024;
			$container->setAttribute('data-chunk-size', (string) max(1, $chunkSize));
		}
		foreach ($this->value as $item) {
			$container->addHtml($this->getItemControlPart($name, $item));
		}
		$row = $this->getItemControlPart($name, Null);
		$row->addHtml($this->getPreloadButtonPart($name));
		$container->addHtml($row);
		$container->addHtml($this->getTransactionControlPart($name));

		return $container;
	}



	/**
	 * Returns container HTML element template.
	 */
	function getContainerPrototype(): Html
	{
		return $this->container;
	}



	function getItemControlPrototype(): Html
	{
		return $this->itemControl;
	}



	function getLabelControlPrototype(): Html
	{
		return $this->labelControl;
	}



	/**
	 * @return Html|string
	 */
	function getUploadControlPrototype()
	{
		return parent::getControl();
	}



	/**
	 * @param FileUploaded|FileCurrent $value
	 */
	private function getCurrentPart(string $name, $value): Html
	{
		$el = clone $this->currentControl;
		$el->value = Utils::serializeFile($value);
		$el->name = $name . '[current][]';
		return $el;
	}



	/**
	 * @param FileUploaded|FileCurrent $src
	 */
	private function getLabelControlPart($src): Html
	{
		if (empty($this->previewer)) {
			$el = clone $this->labelControl;
			$el->value = $src->getName();
			return $el;
		}
		return $this->previewer->getPreviewControlFor($this->store, $this, $src);
	}



	/**
	 * @param FileUploaded|FileCurrent $src
	 */
	private function getUseCheckboxPart(string $name, $src): Html
	{
		$el = clone $this->useCheckbox;
		$el->name = $name . '[use][]';
		$el->value = Utils::serializeFile($src);
		// The name/value must sit on the checkbox itself, otherwise it is not submitted.
		return Html::el('label')->addHtml($el);
	}



	private function getPreloadButtonPart(string $name): Html
	{
		$el = clone $this->preloadButton;
		$el->name = $name . '[preload]';
		return $el;
	}



	/**
	 * @param FileUploaded|FileCurrent|null $value
	 */
	private function getItemControlPart(string $name, $value): Html
	{
		$el = clone $this->itemControl;
		if (empty($value)) {
			$el->addHtml($this->getNewControlPart($name, False));
			$el->appendAttribute("class", $this->formatClass('upload'));
		}
		else {
			$el->addHtml($this->getUseCheckboxPart($name, $value));
			$el->addHtml($this->getCurrentPart($name, $value));
			$el->addHtml($this->getLabelControlPart($value));
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
		// Presence is validated via $name[current]; new uploads via $name[new].
		if ($withoutRequired) {
			unset($el->required);
			$el->setAttribute('data-nette-rules', Utils::removeFilledRules($el->getAttribute('data-nette-rules') ?? []));
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



	private static function assertFileValue(FileCurrent $x): void
	{
		// The parameter type-hint already guarantees the value;
		// kept as an extension point for stricter checks.
	}

}
