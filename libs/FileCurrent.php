<?php declare(strict_types = 1);

/**
 * Copyright (c) since 2004 Martin Takáč (http://martin.takac.name)
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace Taco\Nette\Forms\Controls;

use Nette;


/**
 * An existing file already stored in the system. When replaced, it will be superseded by a new FileUploaded value.
 *
 * @author Martin Takáč <martin@takac.name>
 */
class FileCurrent
{

	use Nette\SmartObject;

	/**
	 * @sample "mp16.jpg"
	 */
	private ?string $label;

	/**
	 * @param string $id Path to the real file. It serves as an identifier. Whether it is a real file
	 * 		that can be loaded is up to the cooperating services. For example FilePreviewer. But usually
	 * 		it will be a good idea. For example: "/tmp/upload-669965256695/mp16.jpg"
	 * @param string $type Mimetype as: "image/jpeg"
	 */
	function __construct(private string $id, private string $type, private int $size, ?string $label = Null)
	{
		$this->label = $label;
		if (empty($this->label)) {
			$this->label = basename($this->id);
		}
	}



	/**
	 * Human-readable display name of the file.
	 */
	function getName(): string
	{
		return $this->label;
	}



	/**
	 * Internal identifier under which the file is stored in the system; also used as the form reference.
	 */
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
