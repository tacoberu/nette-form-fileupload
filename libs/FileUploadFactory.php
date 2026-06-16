<?php declare(strict_types = 1);

/**
 * Copyright (c) since 2010 Martin Takáč (http://martin.takac.name)
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace Taco\Nette\Forms\Controls;

use Stringable;


class FileUploadFactory
{

	/**
	 * Úložiště uchovávající nahrávané soubory před tím, než se skutečně uloží.
	 * Defaultně to je jen temp adresář, viz UploadStoreTemp
	 *
	 * @var UploadStore
	 */
	private $store;

	function __construct(UploadStore $store)
	{
		$this->store = $store;
	}



	/**
	 * @param string|Stringable|null $label
	 */
	function addUploadControl($label = null): FileControl
	{
		return new FileControl($label, $this->store);
	}



	/**
	 * @param string|Stringable|null $label
	 */
	function addMultiUploadControl($label = null): MultipleFileControl
	{
		return new MultipleFileControl($label);
	}

}
