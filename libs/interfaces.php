<?php
/**
 * Copyright (c) since 2004 Martin Takáč (http://martin.takac.name)
 * @license   https://opensource.org/licenses/MIT MIT
 */

namespace Taco\Nette\Forms\Controls;

use Nette\Http\FileUpload;
use Taco\Nette\Http\FileUploaded;


/**
 * Úložiště uchovávající nahrávané soubory před tím, než se skutečně uloží.A repository holding uploaded files before they are actually saved.
 */
interface UploadStore
{

	/**
	 * The unique identifier under which the transaction is registered.
	 * @param int
	 */
	function setId($id);



	/**
	 * The unique identifier under which the transaction is registered.
	 * @return int
	 */
	function getId();



	/**
	 * @param string Filename of uploaded file.
	 * @return bool
	 */
	function exists($filename);



	/**
	 * Move the uploaded file to the directory that represents the transaction. Returns the new location.
	 *
	 * @return FileUploaded
	 */
	function append(FileUpload $file);



	/**
	 * Deleting a directory with a transaction.
	 * @return void
	 */
	function destroy();

}
