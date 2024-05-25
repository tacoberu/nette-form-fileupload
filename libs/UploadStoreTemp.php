<?php
/**
 * Copyright (c) since 2004 Martin Takáč (http://martin.takac.name)
 * @license   https://opensource.org/licenses/MIT MIT
 */

namespace Taco\Nette\Forms\Controls;

use Nette,
	Nette\Utils\Validators,
	Nette\Http\FileUpload;
use RuntimeException;
use LogicException;
use Stringable;
use SplFileInfo;


/**
 * A repository holding uploaded files before they are actually saved. In this case it will just be a different directory.
 */
class UploadStoreTemp implements UploadStore
{

	/**
	 * We subtract this from NOW() so that the number is not so large.
	 */
	const EPOCH_START = 13866047000000;


	/**
	 * A string to prefix the directory for storing files.
	 * "/tmp/upload-669932181976"
	 */
	const PREFIX = 'upload-';


	/**
	 * "/tmp/upload-669932181976"
	 * @var string
	 */
	private $prefix = self::PREFIX;


	/**
	 * The unique identifier under which the transaction is registered.
	 * @var int
	 */
	private $id;


	/**
	 * @param string $prefix A string to prefix the directory for storing files.
	 * @param int $id The identifier of an existing transaction. If not specified, a unique one is generated.
	 */
	function __construct($prefix = Null, $id = Null)
	{
		if ($prefix) {
			Validators::assert($prefix, 'string:1..');
			$this->prefix = $prefix;
		}

		if ($id) {
			$this->setId($id);
		}
	}



	function setId($id)
	{
		Validators::assert($id, 'numeric:1..');
		$this->id = (int)$id;
		return $this;
	}



	function getId()
	{
		if (empty($this->id)) {
			$this->id = (int) (microtime(True) * 10000) - self::EPOCH_START;
		}
		return $this->id;
	}



	function exists($filename)
	{
		return file_exists($filename);
	}



	function append(FileUpload $file)
	{
		$path = $this->baseDir();
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



	function destroy()
	{
		$dir = implode(DIRECTORY_SEPARATOR, $this->baseDir());
		if (file_exists($dir)) {
			self::delete($dir);
		}
	}



	/**
	 * @return array<string>
	 */
	private function baseDir()
	{
		return array(sys_get_temp_dir(), $this->prefix . $this->getId());
	}



	/**
	 * Deletes a file or directory.
	 * @param string $path
	 * @return void
	 * @throws RuntimeException
	 */
	private static function delete($path)
	{
		if (is_file($path) || is_link($path)) {
			$func = DIRECTORY_SEPARATOR === '\\' && is_dir($path) ? 'rmdir' : 'unlink';

			// @ is escalated to exception
			if ( ! @$func($path)) {
				throw new RuntimeException("Unable to delete '$path'.");
			}
		}
		elseif (is_dir($path)) {
			foreach (new \FilesystemIterator($path) as $item) {
				self::delete((string) $item);
			}

			// @ is escalated to exception
			if ( ! @rmdir($path)) {
				throw new RuntimeException("Unable to delete directory '$path'.");
			}
		}
	}

}
