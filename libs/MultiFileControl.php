<?php
/**
 * Copyright (c) since 2004 Martin Takáč (http://martin.takac.name)
 * @license   https://opensource.org/licenses/MIT MIT
 */

namespace Taco\Nette\Forms\Controls;

use Nette,
	Nette\Utils\Html,
	Nette\Utils\Validators,
	Nette\Forms\Form,
	Nette\Forms\Controls\BaseControl,
	Nette\Http\FileUpload;
use RuntimeException;
use LogicException;
use Stringable;


/**
 * File management. In the form, we may already have pre-filled files that we may want to remove/delete. We can freely add and remove files. Nothing is saved (but everything is kept in the transaction) until we save the form.
 *
 * @author Martin Takáč <martin@takac.name>
 */
class MultiFileControl extends BaseControl
{

	const PRELOAD_BUTTON = '__taco_preload';

	/**
	 * List of existing uploaded files.
	 * @var array<mixed>
	 */
	private $uploaded = array();


	/**
	 * List of existing uploaded files to delete.
	 * @var array<mixed>
	 */
	private $remove = array();


	/**
	 * A list of existing uploaded files that have not yet been logged into the system.
	 * @var array<mixed>
	 */
	private $uploading = array();


	/**
	 * Helper for formating mime type class representation uploaded file.
	 * @var function
	 */
	private $parseType;

	/**
	 * A repository holding uploaded files before they are actually saved.
	 * By default it's just a temp directory, see UploadStoreTemp
	 *
	 * @var UploadStore
	 */
	private $store;


	function __construct(string|Stringable|null $label = null, UploadStore $store = Null)
	{
		parent::__construct($label);
		$this->control = Html::el('ul', array(
			'class' => 'file-uploader',
			'data-type' => 'file-uploader',
		));
		$this->parseType = function ($s) {
			if (empty($s)) {
				return $s;
			}

			$p = explode('/', $s, 2);
			return $p[0];
		};

		$this->store = (!empty($store)) ? $store : new UploadStoreTemp();

		$this->monitor(Form::class, function (Form $form): void {
			if ( ! $form->isMethod('post')) {
				throw new Nette\InvalidStateException('File upload requires method POST.');
			}
			$form->getElementPrototype()->enctype = 'multipart/form-data';
			if ( ! isset($form[self::PRELOAD_BUTTON])) {
				$form->addSubmit(self::PRELOAD_BUTTON, 'Preload')->setValidationScope([]);
			}
		});
	}



	/**
	 * Set function for formating mime type class representation uploaded file.
	 *
	 * @param function
	 */
	function setMimeTypeClassFunction($fce)
	{
		$this->parseType = $fce;
	}



	/**
	 * Set control's values.
	 *
	 * @param array of Taco\Nette\Forms\Controls\File $values
	 */
	function setValue($values)
	{
		$this->value = array();
		if ($values && is_array($values)) {
			foreach ($values as $value) {
				$this->value[] = self::assertUploadesFile($value)->setCommited(True);
			}
		}
		return $this;
	}



	/**
	 * Returning values.
	 * @return array of Taco\Nette\Http\FileUploaded | Nette\Http\FileUpload
	 */
	function getValue()
	{
		return array_merge($this->uploaded, $this->remove, (array)$this->value);
	}



	/**
	 * Loads HTTP data. Files moved to transaction.
	 *
	 * @return void
	 */
	function loadHttpData() : void
	{
		$this->value = array();

		$this->store->setId($this->getHttpData(Form::DATA_LINE, '[transaction]'));

		$newfiles = $this->getHttpData(Form::DATA_FILE, '[new][]');

		$uploadedFiles = $this->getHttpData(Form::DATA_LINE, '[uploaded][files][]');
		$uploadedRemove = $this->getHttpData(Form::DATA_LINE, '[uploaded][remove][]');

		$uploadingFiles = $this->getHttpData(Form::DATA_LINE, '[uploading][files][]');
		$uploadingRemove = $this->getHttpData(Form::DATA_LINE, '[uploading][remove][]');

		// Promazávání existujících.
		$this->uploaded = array();
		foreach ($uploadedFiles as $item) {
			$file = self::createFileUploadedFromValue($item);
			$file->setCommited(True);
			if (in_array($item, $uploadedRemove)) {
				$file->setRemove(True);
			}
			$this->value[] = $file;
		}

		// Promazávání transakce.
		foreach ($uploadingFiles as $item) {
			list(, $filename) = explode('#', $item, 2);
			if ( ! in_array($item, $uploadingRemove) && $this->store->exists($filename)) {
				$file = self::createFileUploadedFromValue($item);
				$file->setCommited(False);
				$this->value[] = $file;
			}
		}

		// Ty, co přišli v pořádku, tak uložit do transakce, co nejsou v pořádku zahodit a oznámit neuspěch.
		foreach ($newfiles as $file) {
			if ($file->isOk()) {
				$this->value[] = $this->store->append($file);
			}
			else {
				$this->addError(self::formatError($file));
			}
		}
	}



	function handlePreload()
	{
		// @TODO
	}



	/**
	 * Html representation of control.
	 *
	 * @return Html
	 */
	function getControl()
	{
		$name = $this->getHtmlName();

		$container = clone $this->control;
		//~ $container->setAttribute('data-preload-handle', $this->link('preload!'));
		$parseTypeFunction = $this->parseType;

		// Prvky nahrané už někde na druhé straně
		foreach ($this->value as $item) {
			if ($item->isCommited()) {
				$section = 'uploaded';
			}
			else {
				$section = 'uploading';
			}

			$container
				->addHtml(Html::el('li', array('class' => "file {$section}-file"))
					->addHtml(Html::el('input', array(
						'type' => 'hidden',
						'value' => self::formatValue($item),
						'name' => "{$name}[{$section}][files][]",
					)))
					->addHtml(Html::el('input', array(
						'type' => 'checkbox',
						'checked' => ($item->isRemove()),
						'value' => self::formatValue($item),
						'name' => "{$name}[{$section}][remove][]",
						'title' => strtr('Remove file: %{name}', array(
							'%{name}' => $item->getName()
						)),
					)))
					->addHtml(Html::el('span', array(
						'class' => array('file', $parseTypeFunction($item->getContentType())),
					))->setText($item->getName()))
				);
		}

		// Nový prvek
		return $container
			->addHtml(Html::el('li', array('class' => 'file new-file'))
				->addHtml(Html::el('input', array(
					'type' => 'file',
					'name' => $name . '[new][]',
					'multiple' => True,
				)))
				->addHtml(Html::el('input', array(
					'type' => 'hidden',
					'name' => $name . '[transaction]',
					'value' => $this->store->getId(),
				)))
			);
	}



	/**
	 * Odstranění adresáře s transakcí.
	 */
	function destroy()
	{
		$this->store->destroy();
		$this->uploading = array();
		foreach ($this->value as $i => $x) {
			if ( ! $x->isCommited()) {
				unset($this->value[$i]);
			}
		}
	}


	private static function assertUploadesFile(FileUploaded $value)
	{
		return $value;
	}



	/**
	 * @param string $s 'image/jpeg'
	 * @return string 'image'
	 */
	private static function parseType($s)
	{
		if (empty($s)) {
			return $s;
		}

		$p = explode('/', $s, 2);
		return $p[0];
	}



	/**
	 * @param FileUploaded $s
	 * @return string 'image'
	 */
	private static function formatValue($s)
	{
		return $s->getContentType() . '#' . $s->getPath();
	}



	private static function formatError(FileUpload $file): string
	{
		switch ($file->error) {
			case UPLOAD_ERR_OK:
				throw LogicException('No error.');
			case UPLOAD_ERR_INI_SIZE:
				$message = "The uploaded file exceeds the upload_max_filesize directive in php.ini";
				break;
			case UPLOAD_ERR_FORM_SIZE:
				$message = "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form";
				break;
			case UPLOAD_ERR_PARTIAL:
				$message = "The uploaded file was only partially uploaded";
				break;
			case UPLOAD_ERR_NO_FILE:
				$message = "No file was uploaded";
				break;
			case UPLOAD_ERR_NO_TMP_DIR:
				$message = "Missing a temporary folder";
				break;
			case UPLOAD_ERR_CANT_WRITE:
				$message = "Failed to write file to disk";
				break;
			case UPLOAD_ERR_EXTENSION:
				$message = "File upload stopped by extension";
				break;
			default:
				$message = "Unknown upload error";
				break;
		}

		return "{$file->name}: {$message}";
	}



	/**
	 * @param string $s 'image/jpeg#tasks/6s3qva8l/4728-05.jpg'
	 */
	private static function createFileUploadedFromValue($s): FileUploaded
	{
		$s = explode('#', $s, 2);
		return new FileUploaded($s[1], $s[0]);
	}

}
