<?php
/**
 * Copyright (c) since 2010 Martin Takáč (http://martin.takac.name)
 * @license   https://opensource.org/licenses/MIT MIT
 */

namespace Taco\Nette\Forms\Controls;

use Nette\Forms\Form;
use Nette\Forms\Controls\UploadControl as NetteUploadControl;
use Nette\Http\FileUpload;
use Nette\Utils\Arrays;
use Stringable;



/**
 * If we have the file already uploaded, we can
 * - just want to display it (edit other details),
 * - or want to delete the file,
 * - or want to replace the file with another version.
 *
 * @author Martin Takáč <martin@takac.name>
 */
class FileUploadControl extends NetteUploadControl
{

	function __construct(string|Stringable|null $label = null)
	{
		parent::__construct($label, false);
		$this->setHtmlAttribute('data-taco-type', 'fileupload');
	}



	function loadHttpData(): void
	{
		if ($value = $this->getHttpData(Form::DataFile)) {
			$this->value = new NewFileUploadValue($value);
		}
		elseif ($value = $this->getHttpData(Form::DataText)) {
			$this->value = new CurrentFileUploadValue($value);
		}
		else {
			$this->value = new NewFileUploadValue(new FileUpload([]));
		}
	}



	/**
	 * Have been all files successfully uploaded?
	 */
	function isOk(): bool
	{
		return $this->value instanceof FileUploadValue
			? $this->value->isOk()
			: $this->value && Arrays::every($this->value, fn(FileUploadValue $upload): bool => $upload->isOk());
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
		if ($value instanceof FileUpload) {
			$value = new NewFileUploadValue($value);
		}
		elseif (is_string($value) && strlen($value)) {
			$value = new CurrentFileUploadValue($value);
		}
		$this->setValueInternal($value);
		return $this;
	}



	private function setValueInternal(?FileUploadValue $val): void
	{
		if ($val && $val instanceof NewFileUploadValue) {
			$this->control->type = 'file';
		}
		elseif ($val && $val instanceof CurrentFileUploadValue) {
			$this->control->type = 'text';
			$this->control->value = $val->getValue();
		}
		$this->value = $val;
	}

}



interface FileUploadValue
{
	function isOk(): bool;

	function isFilled(): bool;

	function getSize(): int;

	function getError(): int;

}



class NewFileUploadValue implements FileUploadValue
{
	private FileUpload $file;

	function __construct(FileUpload $file)
	{
		$this->file = $file;
	}



	function isOk(): bool
	{
		return $this->file->isOk();
	}



	/**
	 * Has been any file uploaded?
	 */
	function isFilled(): bool
	{
		return $this->file->getError() !== UPLOAD_ERR_NO_FILE; // ignore null object
		//~ return $this->file instanceof FileUpload
			//~ ? $this->file->getError() !== UPLOAD_ERR_NO_FILE // ignore null object
			//~ : (bool) $this->file->value;
			//~ : true;
	}



	function getSize(): int
	{
		return $this->file->getSize();
	}



	function getError(): int
	{
		return $this->file->getError();
	}



	function getFileUpload(): FileUpload
	{
		return $this->file;
	}

}



class CurrentFileUploadValue implements FileUploadValue
{

	private string $filename;

	function __construct(string $filename)
	{
		$this->filename = $filename;
	}


	function isOk(): bool
	{
		return True;
	}


	function isFilled(): bool
	{
		return True;
	}


	function getSize(): int
	{
		return 1;
	}


	function getError(): int
	{
		return 0;
	}


	function getValue(): string
	{
		return $this->filename;
	}

}
