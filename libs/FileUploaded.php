<?php declare(strict_types = 1);

/**
 * Copyright (c) since 2004 Martin Takáč (http://martin.takac.name)
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace Taco\Nette\Forms\Controls;

use Nette;


/**
 * Recorded, or file being recorded.
 *
 * @author Martin Takáč <martin@takac.name>
 */
class FileUploaded
{

	use Nette\SmartObject;

	/**
	 * @var string
	 */
	private $path;

	/**
	 * @var string
	 */
	private $type;

	/**
	 * @sample "mp16.jpg"
	 */
	private ?string $name;

	/**
	 * If $committed == True && $remove == True - The file uploaded to the system to be deleted.
	 * If $committed == False && $remove == True - The file uploaded to the transaction to be removed from the transaction.
	 */
	private bool $remove = False;

	/**
	 * @param string $path Path to the real file. It serves as an identifier. Whether it is a real
	 * 		file that can be loaded is up to the cooperating services. For example FilePreviewer.
	 * 		But usually it will be a good idea. For example: "/tmp/upload-669965256695/mp16.jpg"
	 * @param string $type Mimetype as: "image/jpeg"
	 */
	function __construct($path, $type, ?string $name = Null)
	{
		$this->path = $path;
		$this->type = $type;
		$this->name = $name;
		if ($this->name === null || $this->name === '' || $this->name === '0') {
			$this->name = basename($this->path);
		}
	}



	function getName(): ?string
	{
		return $this->name;
	}



	function getTemporaryFile(): string
	{
		return $this->path;
	}



	function getPath(): string
	{
		return $this->path;
	}



	function getId(): string
	{
		return $this->path;
	}



	function isRemove(): bool
	{
		return $this->remove;
	}



	function setRemove(bool $val = True): self
	{
		$this->remove = $val;
		return $this;
	}



	function getContentType(): string
	{
		return $this->type;
	}



	/**
	 * Has been any file uploaded?
	 */
	function isFilled(): bool
	{
		return ! $this->remove;
	}



	function getSize(): int
	{
		return 1;
	}



	function getError(): int
	{
		return 0;
	}

}
