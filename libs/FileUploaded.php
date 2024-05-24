<?php
/**
 * Copyright (c) since 2004 Martin Takáč (http://martin.takac.name)
 * @license   https://opensource.org/licenses/MIT MIT
 */

namespace Taco\Nette\Http;

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
	 * @sample "mp16.jpg"
	 * @var string
	 */
	private $name;


	/**
	 * @sample "/tmp/upload-669965256695/mp16.jpg"
	 * @var string
	 */
	private $path;


	/**
	 * @sample "image/jpeg"
	 * @var string
	 */
	private $type;


	/**
	 * Whether the file has been saved to the system (True) or is only in the transaction (False).
	 * @var boolean
	 */
	private $commited = False;


	/**
	 * If $committed == True && $remove == True - The file uploaded to the system to be deleted.
	 * If $committed == False && $remove == True - The file uploaded to the transaction to be removed from the transaction.
	 * @var boolean
	 */
	private $remove = False;


	/**
	 * @param string $path Path to the real file. It serves as an identifier. Whether it is a real file that can be loaded is up to the cooperating services. For example FilePreviewer. But usually it will be a good idea.
	 * @param string $type Mimetype as: "image/jpeg"
	 */
	function __construct($path, $type, $name = Null)
	{
		$this->path = $path;
		$this->type = $type;
		if (empty($this->name)) {
			$this->name = basename($path);
		}
	}



	/**
	 * @return string
	 */
	function getName()
	{
		return $this->name;
	}



	/**
	 * @return string
	 */
	function getTemporaryFile()
	{
		return $this->path;
	}



	/**
	 * @return string
	 */
	function getPath()
	{
		return $this->path;
	}



	/**
	 * @return string
	 */
	function getId()
	{
		return $this->path;
	}



	/**
	 * @return boolean
	 */
	function isCommited()
	{
		return $this->commited;
	}



	/**
	 * @return boolean
	 */
	function isRemove()
	{
		return $this->remove;
	}



	/**
	 * @param boolean
	 */
	function setCommited($m = True)
	{
		$this->commited = $m;
		return $this;
	}



	/**
	 * @param boolean
	 */
	function setRemove($m = True)
	{
		$this->remove = $m;
		return $this;
	}



	/**
	 * @return string
	 */
	function getContentType()
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
