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
			$type = strtolower($file->getContentType());
			if (!in_array($type, $mimeTypes, true) && !in_array(preg_replace('#/.*#', '/*', $type), $mimeTypes, true)) {
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



	/**
	 * Detects when PHP silently discarded the entire POST body because the total
	 * upload size exceeded post_max_size, and adds a form-level error.
	 *
	 * Why not use Form::validateMaxPostSize()?
	 * Nette already has this method, but it is gated behind $form->submittedBy,
	 * which is never set in this scenario. The chain is:
	 *
	 *   post_max_size exceeded
	 *     → PHP empties $_POST and $_FILES before any PHP code runs
	 *     → Form::receiveHttpData() gets an empty array, CSRF token is missing
	 *     → returns null → submittedBy = false → isSubmitted() = false
	 *     → fireEvents() returns immediately, validate() is never called
	 *     → validateMaxPostSize() is never reached
	 *
	 * Even calling validateMaxPostSize() directly would not help because it has
	 * its own early return: `if (!$this->submittedBy ...) return;`
	 *
	 * This method avoids the problem by running inside a monitor() callback,
	 * which fires when the control is anchored to the form — before Nette
	 * attempts to determine whether the form was submitted. At that point
	 * Content-Length is still readable from $_SERVER and the comparison
	 * Content-Length > post_max_size is a deterministic indicator that PHP
	 * discarded the body (no other situation produces this condition).
	 *
	 * An array keyed by spl_object_id is used so the check runs only once per form
	 * even when multiple file controls are present.
	 */
	static function checkPostMaxSize(Form $form): void
	{
		static $checked = [];
		$key = spl_object_id($form);
		if (isset($checked[$key])) {
			return;
		}
		$checked[$key] = true;
		if (strtoupper((string) (filter_input(INPUT_SERVER, 'REQUEST_METHOD') ?? '')) !== 'POST') {
			return;
		}
		$contentLength = (int) (filter_input(INPUT_SERVER, 'CONTENT_LENGTH') ?? 0);
		$maxSize = Forms\Helpers::iniGetSize('post_max_size');
		if ($maxSize > 0 && $contentLength > $maxSize) {
			$form->addError(sprintf(Forms\Validator::$messages[Form::MaxFileSize], $maxSize));
		}
	}



	/**
	 * @return array<FileUploaded|FileCurrent>
	 */
	private static function getValueFrom(Control $src): array
	{
		$xs = $src->getValue();
		if (!is_array($xs)) {
			$xs = [$xs];
		}
		return $xs;
	}

}
