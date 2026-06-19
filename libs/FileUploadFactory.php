<?php declare(strict_types = 1);

/**
 * Copyright (c) since 2010 Martin Takáč (http://martin.takac.name)
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace Taco\Nette\Forms\Controls;

class FileUploadFactory
{

	/**
	 * Storage for uploaded files before they are committed to the system.
	 * By default just a temp directory; see UploadStoreTemp.
	 *
	 * @var UploadStore
	 */
	private $store;

	function __construct(UploadStore $store)
	{
		$this->store = $store;
	}



	/**
	 * @param string|null $label
	 */
	function addUploadControl($label = null): FileControl
	{
		return new FileControl($label, $this->store);
	}



	/**
	 * @param string|null $label
	 */
	function addMultiUploadControl($label = null): MultiFileControl
	{
		return new MultiFileControl($label, $this->store);
	}

}
