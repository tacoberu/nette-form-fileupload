<?php declare(strict_types = 1);

/**
 * Copyright (c) since 2004 Martin Takáč (http://martin.takac.name)
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace Taco\Nette\Forms\Controls;

use Nette\Http\FileUpload;
use Nette\Utils\AssertionException;
use PHPUnit\Framework\TestCase;
use FilesystemIterator;


class UploadStoreTempTest extends TestCase
{

	private string $baseDir;

	function testGetIdIsStableAndPositive()
	{
		$store = new UploadStoreTemp(null, null, null, 0);
		$id = $store->getId();
		$this->assertGreaterThan(0, $id);
		// Repeated calls return the same generated id.
		$this->assertSame($id, $store->getId());
	}



	function testSetIdIsFluentAndUsed()
	{
		$store = new UploadStoreTemp(null, null, null, 0);
		$this->assertSame($store, $store->setId(456));
		$this->assertSame(456, $store->getId());
	}



	function testConstructorIdIsUsed()
	{
		$store = $this->createStore(789);
		$this->assertSame(789, $store->getId());
	}



	function testSetIdRejectsNonPositiveId()
	{
		$store = new UploadStoreTemp(null, null, null, 0);
		$this->expectException(AssertionException::class);
		$store->setId(-1);
	}



	function testSetIdIgnoresEmptyTransaction()
	{
		$store = new UploadStoreTemp(null, null, null, 0);
		// An empty transaction (0/null, e.g. a partial request) is ignored;
		// a fresh id is generated instead.
		$this->assertSame($store, $store->setId(0));
		$this->assertSame($store, $store->setId(Null));
		$this->assertGreaterThan(0, $store->getId());
	}



	function testAppendMovesFileIntoTransactionDir()
	{
		$store = $this->createStore(123);
		$src = $this->baseDir . '/source.bin';
		file_put_contents($src, 'hello world');
		$file = $this->upload('mp16.jpg', $src);
		$dest = $this->baseDir . DIRECTORY_SEPARATOR . 'trx-123'
			. DIRECTORY_SEPARATOR . $file->getSanitizedName();

		$result = $store->append($file);

		$this->assertInstanceOf(FileUploaded::class, $result);
		$this->assertFileExists($dest);
		$this->assertFileDoesNotExist($src); // the source was moved away
		$this->assertSame('hello world', file_get_contents($dest));
		$this->assertSame($file->getSanitizedName(), $result->getId());
		$this->assertSame('mp16.jpg', $result->getName()); // original (unsanitized) name
	}



	function testDestroyRemovesTransactionDir()
	{
		$store = $this->createStore(123);
		$src = $this->baseDir . '/source.bin';
		file_put_contents($src, 'data');
		$store->append($this->upload('a.txt', $src));
		$transactionDir = $this->baseDir . '/trx-123';
		$this->assertDirectoryExists($transactionDir);

		$store->destroy();

		$this->assertDirectoryDoesNotExist($transactionDir);
	}



	function testDestroyOnEmptyTransactionIsNoop()
	{
		$store = $this->createStore(999);
		$store->destroy(); // nothing appended yet
		$this->assertDirectoryDoesNotExist($this->baseDir . '/trx-999');
	}



	function testCalculateAgeOfId()
	{
		$now = (int) (microtime(True) * 10000) - UploadStoreTemp::EPOCH_START;
		$this->assertEqualsWithDelta(0, UploadStoreTemp::calculateAgeOfId($now), 2);

		$oneMinuteAgo = $now - 60 * 10000;
		$this->assertEqualsWithDelta(60, UploadStoreTemp::calculateAgeOfId($oneMinuteAgo), 2);
	}



	protected function setUp(): void
	{
		$this->baseDir = sys_get_temp_dir() . '/uploadstore-test-' . uniqid();
		mkdir($this->baseDir);
	}



	protected function tearDown(): void
	{
		$this->removeRecursively($this->baseDir);
	}



	/**
	 * gcAgeLimit:0 disables the destructor GC, so tests don't scan/delete the filesystem.
	 */
	private function createStore(int $id = 123): UploadStoreTemp
	{
		return new UploadStoreTemp('trx-', $id, $this->baseDir, 0);
	}



	private function upload(string $name, string $tmpName): FileUpload
	{
		return new FileUpload([
			'name' => $name,
			'full_path' => $name,
			'size' => (int) filesize($tmpName),
			'tmp_name' => $tmpName,
			'error' => UPLOAD_ERR_OK,
		]);
	}



	private function removeRecursively(string $dir): void
	{
		if (!is_dir($dir)) {
			return;
		}
		foreach (new FilesystemIterator($dir) as $item) {
			if ($item->isDir()) {
				$this->removeRecursively((string) $item);
			}
			else {
				unlink((string) $item);
			}
		}
		rmdir($dir);
	}

}
