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
	 * @sample "mp16.jpg"
	 */
	private ?string $label;

	/**
	 * @param string $id Path to the real file. It serves as an identifier. Whether it is a real
	 * 		file that can be loaded is up to the cooperating services. For example FilePreviewer.
	 * 		But usually it will be a good idea. For example: "/tmp/upload-669965256695/mp16.jpg"
	 * @param string $type Mimetype as: "image/jpeg"
	 */
	function __construct(private $id, private $type, private int $size, ?string $label = Null)
	{
		$this->label = $label;
		if (empty($this->label)) {
			$this->label = basename($this->id);
		}
	}



	function getName(): string
	{
		return $this->label;
	}



	function getId(): string
	{
		return $this->id;
	}



	function getContentType(): string
	{
		return $this->type;
	}



	function getSize(): int
	{
		return $this->size;
	}

}
