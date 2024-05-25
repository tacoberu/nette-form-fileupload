<?php
/**
 * Copyright (c) since 2004 Martin Takáč (http://martin.takac.name)
 * @license   https://opensource.org/licenses/MIT MIT
 */

namespace Taco\Nette\Forms\Controls;

use LogicException;
use Nette\Utils\Html;
use Nette\Utils\Image;
use Nette\Utils\ImageColor;
use Nette\Utils\ImageType;


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
	private int $flag = Image::ShrinkOnly | Image::Stretch;

	/**
	 * @var positive-int
	 */
	private int $quality = 80;

	/**
	 * @var 1|2|3|6|18|19
	 */
	private $format = ImageType::JPEG;


	function getPreviewControlFor(FileUploaded|FileCurrent $val): Html
	{
		if (self::isImageTypeByFilename($val->getId())) {
			$image = Image::fromFile($val->getId());
			$image->resize($this->width, $this->height, $this->flag);
			$content = $image->toString($this->format, $this->quality);
		}
		else {
			$image = Image::fromBlank($this->width, $this->height, Image::rgb(50, 190, 212));
			// @phpstan-ignore-next-line
			$image->string(8, 8, 8, self::getFileExtension($val->getId()), $image->colorAllocate(0,0,0));
			$content = $image->toString($this->format, $this->quality);
		}
		return Html::el('img')
			->setAttribute('src', 'data:' . Image::typeToMimeType($this->format) . ';base64, ' . base64_encode($content))
			->setAttribute('alt', $val->getName());
	}



	private static function isImageTypeByFilename(string $file): bool
	{
		$ext = self::getFileExtension($file);
		if (!in_array($ext, [
				'jpeg', 'jpg', 'jpe',
				'gif',
				'png',
				'webp',
				'avif',
				'bmp',
				], True)) {
			return False;
		}
		return Image::isTypeSupported(Image::extensionToType($ext));
	}



	private static function getFileExtension(string $file): string
	{
		return strtolower(pathinfo($file, PATHINFO_EXTENSION));
	}

}
