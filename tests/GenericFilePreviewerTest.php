<?php declare(strict_types = 1);

/**
 * Copyright (c) since 2004 Martin Takáč (http://martin.takac.name)
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace Taco\Nette\Forms\Controls;

use Nette\Utils\Html;
use PHPUnit\Framework\TestCase;


class GenericFilePreviewerTest extends TestCase
{

	private const ImageFile = __DIR__ . '/../examples/document_root/img/the-cat.jpeg';

	function testPreviewForNonImageFile()
	{
		$previewer = new GenericFilePreviewer(__DIR__ . '/../examples');
		$store = $this->createMock(UploadStore::class);
		$control = $this->createMock(FileControl::class);
		$html = $previewer->getPreviewControlFor(
			$store,
			$control,
			new FileCurrent('uploaded/report.pdf', 'application/pdf', 0)
		);

		$this->assertPreview($html, 'report.pdf');
	}



	function testPreviewForImageFile()
	{
		$basePath = __DIR__ . '/../examples/document_root/img';
		$previewer = new GenericFilePreviewer($basePath);
		$store = $this->createMock(UploadStore::class);
		$control = $this->createMock(FileControl::class);
		$html = $previewer->getPreviewControlFor(
			$store,
			$control,
			new FileCurrent('the-cat.jpeg', 'image/jpeg', 0)
		);

		$this->assertPreview($html, 'the-cat.jpeg');
	}



	function testPreviewForFileUploaded()
	{
		$previewer = new GenericFilePreviewer(__DIR__ . '/../examples');
		$store = $this->createMock(UploadStore::class);
		$store->method('getRealPathFrom')->willReturn(self::ImageFile);
		$control = $this->createMock(FileControl::class);
		$html = $previewer->getPreviewControlFor(
			$store,
			$control,
			new FileUploaded('the-cat.jpeg', 'image/jpeg', 0, 'kitten.jpeg')
		);

		$this->assertPreview($html, 'kitten.jpeg');
	}



	function testPreviewForMissingImageFallsBackToGenericIcon()
	{
		$previewer = new GenericFilePreviewer(__DIR__ . '/../examples');
		$store = $this->createMock(UploadStore::class);
		$control = $this->createMock(FileControl::class);
		$html = $previewer->getPreviewControlFor(
			$store,
			$control,
			new FileCurrent('uploaded/account/56695/missing.jpg', 'image/jpeg', 0)
		);

		$this->assertPreview($html, 'missing.jpg');
	}



	protected function setUp(): void
	{
		if (!extension_loaded('gd')) {
			$this->markTestSkipped('The gd extension is required.');
		}
	}



	/**
	 * The result is always an <img> with a base64 JPEG data URI and the file name in alt.
	 */
	private function assertPreview(Html $html, string $alt): void
	{
		$this->assertInstanceOf(Html::class, $html);
		$this->assertSame('img', $html->getName());
		$this->assertSame($alt, $html->getAttribute('alt'));

		$src = $html->getAttribute('src');
		$this->assertStringStartsWith('data:image/jpeg;base64,', $src);

		$binary = base64_decode(explode('base64, ', $src, 2)[1], true);
		$this->assertNotFalse($binary, 'src must contain valid base64');
		// JPEG magic bytes.
		$this->assertStringStartsWith("\xFF\xD8\xFF", $binary);
	}

}
