<?php
/**
 * Copyright (c) since 2004 Martin Takáč (http://martin.takac.name)
 * @license   https://opensource.org/licenses/MIT MIT
 */

namespace Taco\Nette\Forms\Controls;

use Nette\Forms\Form;
use Nette\Http\FileUpload;
use LogicException;


class Utils
{

	/**
	 * @return string 'image/jpeg#tasks/6s3qva8l/4728-05.jpg'
	 */
	static function serializeFile(FileUploaded|FileCurrent $src): string
	{
		return $src->getContentType() . '#' . $src->getId();
	}



	/**
	 * @param string $src 'image/jpeg#tasks/6s3qva8l/4728-05.jpg'
	 */
	static function createFileUploadedFromValue(string $src): FileUploaded
	{
		list($type, $path) = explode('#', $src, 2);
		return new FileUploaded($path, $type);
	}



	/**
	 * @param string $src 'image/jpeg#tasks/6s3qva8l/4728-05.jpg'
	 */
	static function createFileCurrentFromValue(string $src): FileCurrent
	{
		list($type, $path) = explode('#', $src, 2);
		return new FileCurrent($path, $type);
	}



	/**
	 * @param array<mixed> $xs
	 * @return array<mixed>
	 */
	static function removeFilledRules(array $xs): array
	{
		foreach ($xs as $i => $x) {
			if ($x['op'] === Form::Filled) {
				unset($xs[$i]);
			}
			elseif (isset($x['rules'])) {
				$xs[$i]['rules'] = self::removeFilledRules($x['rules']);
			}
		}
		return array_values($xs);
	}



	static function formatError(FileUpload $file): string
	{
		switch ($file->error) {
			case UPLOAD_ERR_OK:
				throw new LogicException('No error.');
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

		return "{$file->getName()}: {$message}";
	}

}
