<?php declare(strict_types = 1);

/**
 * Copyright (c) since 2004 Martin Takáč (http://martin.takac.name)
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace Taco\Nette\Forms\Controls;

use Nette\Utils\Html;
use Nette\Utils\Image;
use Nette\Utils\ImageException;


/**
 * We want to represent the uploaded file with a nice icon.
 *
 * @author Martin Takáč <martin@takac.name>
 */
class GenericFilePreviewer implements FilePreviewer
{

	/**
	 * @var positive-int
	 */
	private int $width = 128;

	/**
	 * @var positive-int
	 */
	private int $height = 128;

	/**
	 * @var int<0, 15>
	 */
	private int $flag = Image::SHRINK_ONLY | Image::STRETCH;

	/**
	 * @var positive-int
	 */
	private int $quality = 80;

	/**
	 * @var 1|2|3|6|18|19
	 */
	private int $format = Image::JPEG;

	/**
	 * @param FileUploaded | FileCurrent $val
	 */
	function getPreviewControlFor($val): Html
	{
		$content = self::isImageTypeByFilename($val->getId())
			? $this->renderContentFor($val)
			: $this->renderDefaultImageContent($val);
		return Html::el('img')
			->setAttribute('src', 'data:' . Image::typeToMimeType($this->format) . ';base64, ' . base64_encode($content))
			->setAttribute('alt', $val->getName());
	}



	/**
	 * Renders a thumbnail of the image, or a generic icon when the file
	 * is not a loadable image (wrong type, missing or unreadable file).
	 * @param FileUploaded | FileCurrent $src
	 */
	private function renderContentFor($src): string
	{
		try {
			$image = Image::fromFile($src->getId());
			$image->resize($this->width, $this->height, $this->flag);
			return $image->toString($this->format, $this->quality);
		}
		catch (ImageException $exception) {
			return $this->renderDefaultImageContent($src);
		}
	}



	/**
	 * @param FileUploaded | FileCurrent $src
	 */
	private function renderDefaultImageContent($src): string
	{
		$image = Image::fromBlank($this->width, $this->height, Image::rgb(190, 190, 190));
		$image->string(8, 8, 8, self::getFileExtension($src->getId()), $image->colorAllocate(0, 0, 0));
		return $image->toString($this->format, $this->quality);
	}



	private static function isImageTypeByFilename(string $file): bool
	{
		$ext = self::getFileExtension($file);
		if (!in_array($ext, ['jpeg', 'jpg', 'jpe', 'gif', 'png', 'webp', 'avif', 'bmp',], True)) {
			return False;
		}
		return self::isTypeSupported(Image::extensionToType($ext));
	}



	/**
	 * Whether the GD extension can handle the given Image type. Replaces
	 * Image::isTypeSupported(), which is only available in nette/utils 4.x.
	 */
	private static function isTypeSupported(int $type): bool
	{
		$support = imagetypes();
		switch ($type) {
			case Image::JPEG:
				return (bool) ($support & IMG_JPG);
			case Image::PNG:
				return (bool) ($support & IMG_PNG);
			case Image::GIF:
				return (bool) ($support & IMG_GIF);
			case Image::WEBP:
				return (bool) ($support & IMG_WEBP);
			case Image::BMP:
				return (bool) ($support & IMG_BMP);
			case Image::AVIF:
				return defined('IMG_AVIF') && (bool) ($support & IMG_AVIF);
			default:
				return False;
		}
	}



	private static function getFileExtension(string $file): string
	{
		return strtolower(pathinfo($file, PATHINFO_EXTENSION));
	}

}
