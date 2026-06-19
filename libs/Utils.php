<?php declare(strict_types = 1);

/**
 * Copyright (c) since 2004 Martin Takáč (http://martin.takac.name)
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace Taco\Nette\Forms\Controls;

use Nette\Forms\Form;
use Nette\Forms\Control;
use Nette\Forms;
use Nette\Http\FileUpload;
use Nette\Utils\Json;
use LogicException;


class Utils
{

	const KindUploaded = 'u';
	const KindCurrent = 'c';

	/**
	 * @param FileUploaded|FileCurrent $src
	 * @return string JSON-encoded file reference
	 */
	static function serializeFile($src): string
	{
		$kind = $src instanceof FileUploaded
			? self::KindUploaded
			: self::KindCurrent;
		return Json::encode([
			$kind,
			$src->getContentType(),
			$src->getSize(),
			$src->getId(),
			$src->getName(),
		]);
	}



	/**
	 * @param string $src json ['c', 'image/jpeg', 42, 'tasks/6s3qva8l/4728-05.jpg', 'Jmeno souboru.jpg']
	 * @return FileCurrent|FileUploaded|null
	 */
	static function createFileValueFromRaw(string $src)
	{
		if (list($kind, $type, $size, $path, $label) = Json::decode($src)) {
			if ($kind === self::KindCurrent) {
				return new FileCurrent($path, $type, (int) $size, $label);
			}
			if ($kind === self::KindUploaded) {
				return new FileUploaded($path, $type, (int) $size, $label);
			}
		}

		return Null;
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



	/**
	 * Replacement for Nette\Forms\Validator::validateFileSize() — accepts any Control,
	 * not just UploadControl, so it works with our FileControl / MultiFileControl.
	 * Use [Utils::class, 'validateFileSize'] as the rule validator to allow removeRule() to find it.
	 *
	 * @param mixed ...$args
	 */
	static function validateFileSize(Control $control, ...$args): bool
	{
		$limit = $args[0] ?? PHP_INT_MAX;

		foreach (self::getValueFrom($control) as $file) {
			if ($file->getSize() > $limit) {
				return false;
			}
		}
		return true;
	}



	/**
	 * Replacement for Nette\Forms\Validator::validateMimeType().
	 * See validateFileSize() for variadic rationale.
	 *
	 * @param mixed ...$args
	 */
	static function validateMimeType(Control $control, ...$args): bool
	{
		$mimeType = $args[0] ?? '';
		$mimeTypes = is_array($mimeType)
			? $mimeType
			: explode(',', (string) $mimeType);
		foreach (self::getValueFrom($control) as $file) {
			$type = strtolower($file->getContentType() ?? '');
			if (!in_array($type, $mimeTypes, true)
				&& !in_array(preg_replace('#/.*#', '/*', $type), $mimeTypes, true)
			) {
				return false;
			}
		}
		return true;
	}



	/**
	 * Replacement for Nette\Forms\Validator::validateImage().
	 */
	static function validateImage(Control $control): bool
	{
		$imageTypes = Forms\Helpers::getSupportedImages();
		foreach (self::getValueFrom($control) as $file) {
			if (!in_array($file->getContentType(), $imageTypes, true)) {
				return false;
			}
		}
		return true;
	}



	static function formatError(FileUpload $file): string
	{
		switch ($file->error) {
			case UPLOAD_ERR_OK:
				throw new LogicException('No error.');
			case UPLOAD_ERR_INI_SIZE:
				$limit = Forms\Helpers::iniGetSize('upload_max_filesize');
				$message = sprintf(Forms\Validator::$messages[Form::MaxFileSize], $limit);
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


	private static function getValueFrom(Control $src): array
	{
		$xs = $src->getValue();
		if (!is_array($xs)) {
			$xs = [$xs];
		}
		return $xs;
	}
}
