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
		// A non-image file does not exist on the disk - a generic icon is generated.
		$previewer = new GenericFilePreviewer();
		$html = $previewer->getPreviewControlFor(new FileCurrent('uploaded/report.pdf', 'application/pdf'));

		$this->assertPreview($html, alt: 'report.pdf');
	}



	function testPreviewForImageFile()
	{
		$previewer = new GenericFilePreviewer();
		$html = $previewer->getPreviewControlFor(new FileCurrent(self::ImageFile, 'image/jpeg'));

		$this->assertPreview($html, alt: 'the-cat.jpeg');
	}



	function testPreviewForFileUploaded()
	{
		// The previewer accepts FileUploaded as well.
		$previewer = new GenericFilePreviewer();
		$html = $previewer->getPreviewControlFor(new FileUploaded(self::ImageFile, 'image/jpeg', 'kitten.jpeg'));

		$this->assertPreview($html, alt: 'kitten.jpeg');
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

		$binary = base64_decode(explode('base64, ', $src, 2)[1], strict: True);
		$this->assertNotFalse($binary, 'src must contain valid base64');
		// JPEG magic bytes.
		$this->assertStringStartsWith("\xFF\xD8\xFF", $binary);
	}

}
