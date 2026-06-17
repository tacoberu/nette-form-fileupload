<?php declare(strict_types = 1);

/**
 * Copyright (c) since 2004 Martin Takáč (http://martin.takac.name)
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace Taco\Nette\Forms\Controls;

use Nette\Utils\Html;
use Nette\Utils\Image;
use Nette\Utils\ImageException;
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
	private int $format = ImageType::JPEG;

	function __construct(private readonly string $basePath)
	{
	}



	function getPreviewControlFor(UploadStore $store, FileControl | MultiFileControl $control, FileUploaded | FileCurrent $val): Html
	{
		if ($val instanceof FileUploaded) {
			$content = ($path = $store->getRealPathFrom($val)) && self::isImageTypeByFilename($path)
				? $this->renderContentFor($path)
				: $this->renderDefaultImageContent($path);
		}
		else if ($val instanceof FileCurrent) {
			$path = implode(DIRECTORY_SEPARATOR, [
				$this->basePath,
				$val->getId(),
			]);
			$content = file_exists($path)
				? $this->renderContentFor($path)
				: $this->renderDefaultImageContent($path);
		}

		return Html::el('img')
			->setAttribute('src', 'data:' . Image::typeToMimeType($this->format) . ';base64, ' . base64_encode($content))
			->setAttribute('alt', $val->getName());
	}



	/**
	 * Renders a thumbnail of the image, or a generic icon when the file
	 * is not a loadable image (wrong type, missing or unreadable file).
	 */
	private function renderContentFor(string $path): string
	{
		try {
			$image = Image::fromFile($path);
			$image->resize($this->width, $this->height, $this->flag);
			return $image->toString($this->format, $this->quality);
		}
		catch (ImageException) {
			return $this->renderDefaultImageContent($path);
		}
	}



	private function renderDefaultImageContent(string $path): string
	{
		$image = Image::fromBlank($this->width, $this->height, Image::rgb(190, 190, 190));
		// @phpstan-ignore-next-line
		$image->string(8, 8, 8, self::getFileExtension($path), $image->colorAllocate(0, 0, 0));
		return $image->toString($this->format, $this->quality);
	}



	private static function isImageTypeByFilename(string $file): bool
	{
		$ext = self::getFileExtension($file);
		if (!in_array($ext, ['jpeg', 'jpg', 'jpe', 'gif', 'png', 'webp', 'avif', 'bmp',], True)) {
			return False;
		}
		return Image::isTypeSupported(Image::extensionToType($ext));
	}



	private static function getFileExtension(string $file): string
	{
		return strtolower(pathinfo($file, PATHINFO_EXTENSION));
	}

}
