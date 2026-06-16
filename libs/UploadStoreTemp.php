<?php declare(strict_types = 1);

/**
 * Copyright (c) since 2004 Martin Takáč (http://martin.takac.name)
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace Taco\Nette\Forms\Controls;

use Nette\Utils\Validators;
use Nette\Utils\Strings;
use Nette\Http\FileUpload;
use FilesystemIterator;
use RuntimeException;


/**
 * A repository holding uploaded files before they are actually saved. In this
 * case it will just be a different directory.
 */
class UploadStoreTemp implements UploadStore
{

	/**
	 * We subtract this from NOW() so that the number is not so large.
	 */
	public const EPOCH_START = 13866047000000;

	/**
	 * A string to prefix the directory for storing files.
	 * "/tmp/upload-669932181976"
	 */
	public const PREFIX = 'upload-';

	/**
	 * "/tmp/upload-669932181976"
	 */
	private string $prefix = self::PREFIX;

	/**
	 * The unique identifier under which the transaction is registered.
	 */
	private ?int $id = null;

	private ?string $baseDir;

	/**
	 * 60 = minute
	 */
	private int $gcLimit;

	private int $gcMaxCount;

	/**
	 * In sec
	 */
	static function calculateAgeOfId(int $id): int
	{
		return (int) ((self::generateId() - $id) / 10000);
	}



	/**
	 * @param string $prefix A string to prefix the directory for storing files.
	 * @param int $id The identifier of an existing transaction. If not specified, a unique one is generated.
	 * @param string $baseDir Umístění úložiště. Null = sys_get_temp_dir()
	 * @param int $gcAgeLimit How old must a transaction be to be deleted.
	 * @param int $gcMaxCount Maximum number of transactions to delete. In order to spread the load over time.
	 */
	function __construct($prefix = Null
		, $id = Null
		, ?string $baseDir = Null
		, int $gcAgeLimit = 60 * 60 * 24 * 3
		, int $gcMaxCount = 5
		)
	{
		if ($prefix) {
			Validators::assert($prefix, 'string:1..');
			$this->prefix = $prefix;
		}

		if ($id) {
			$this->setId($id);
		}
		$this->baseDir = $baseDir;
		$this->gcLimit = $gcAgeLimit;
		$this->gcMaxCount = $gcMaxCount;
	}



	/**
	 * It will serve as a GC for erasing old records.
	 */
	function __destruct()
	{
		if (empty($this->gcLimit)) {
			return;
		}
		$path = implode('/', array_merge([$this->getBaseDir()], array_slice(explode('/', $this->prefix), 0, -1)));
		$pathWithPrefix = $this->getBaseDir() . DIRECTORY_SEPARATOR . $this->prefix;
		$count = $this->gcMaxCount;
		foreach (new FilesystemIterator($path) as $item) {
			$item = (string) $item;
			if (Strings::startsWith($item, $pathWithPrefix)) {
				if ($count-- < 0) {
					break;
				}
				$id = (int) substr($item, strlen($pathWithPrefix));
				if ($id !== $this->id && $this->itIsOld($id)) {
					self::delete($item);
				}
			}
		}
	}



	function setId(?int $id): self
	{
		// An empty transaction (e.g. a partial request) is ignored - getId() generates a fresh one.
		if (empty($id)) {
			return $this;
		}
		Validators::assert($id, 'numeric:1..');
		$this->id = (int) $id;
		return $this;
	}



	function getId(): int
	{
		if (empty($this->id)) {
			$this->id = self::generateId();
		}
		return $this->id;
	}



	function exists($filename): bool
	{
		return file_exists($filename);
	}



	function append(FileUpload $file): FileUploaded
	{
		$path = $this->getTransactionDir();
		$path[] = $file->sanitizedName;
		$path = implode(DIRECTORY_SEPARATOR, $path);

		// Vytvořit, pokud neexistuje.
		$dir = dirname($path);
		if ( ! file_exists($dir)) {
			mkdir($dir, 0777, True);
		}

		$file->move($path);
		return new FileUploaded($file->temporaryFile, $file->contentType, $file->name);
	}



	function destroy(): void
	{
		$dir = implode(DIRECTORY_SEPARATOR, $this->getTransactionDir());
		if (file_exists($dir)) {
			self::delete($dir);
		}
	}



	private function itIsOld(int $id): bool
	{
		return self::calculateAgeOfId($id) > $this->gcLimit;
	}



	/**
	 * @return array<string>
	 */
	private function getTransactionDir(): array
	{
		return [$this->getBaseDir()
			, $this->prefix . $this->getId(),
		];
	}



	private function getBaseDir(): string
	{
		return $this->baseDir ?: sys_get_temp_dir();
	}



	private static function generateId(): int
	{
		return (int) (microtime(True) * 10000) - self::EPOCH_START;
	}



	/**
	* Deletes a file or directory.
	* @throws RuntimeException
	*/
	private static function delete(string $path): void
	{
		if (is_file($path) || is_link($path)) {
			$func = DIRECTORY_SEPARATOR === '\\' && is_dir($path)
				? 'rmdir'
				: 'unlink';

			// @ is escalated to exception
			if ( ! @$func($path)) {
				throw new RuntimeException("Unable to delete '$path'.");
			}
		}
		elseif (is_dir($path)) {
			foreach (new FilesystemIterator($path) as $item) {
				self::delete((string) $item);
			}

			// @ is escalated to exception
			if ( ! @rmdir($path)) {
				throw new RuntimeException("Unable to delete directory '$path'.");
			}
		}
	}

}
