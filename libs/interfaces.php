<?php declare(strict_types = 1);

/**
 * Copyright (c) since 2004 Martin Takáč (http://martin.takac.name)
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace Taco\Nette\Forms\Controls;

use Nette\Http\FileUpload;
use Nette\Utils\Html;


/**
 * A repository holding uploaded files before they are actually saved.
 */
interface UploadStore
{

	/**
	 * The unique identifier under which the transaction is registered.
	 */
	function setId(?int $id): self;



	/**
	 * The unique identifier under which the transaction is registered.
	 */
	function getId(): int;



	/**
	 * @param string $filename Filename of uploaded file.
	 */
	function exists(string $filename): bool;



	/**
	 * Move the uploaded file to the directory that represents the transaction. Returns the new location.
	 */
	function append(FileUpload $file): FileUploaded;



	/**
	 * Deleting a directory with a transaction.
	 */
	function destroy(): void;

}



/**
 * We want to represent the uploaded file with a nice icon.
 *
 * @author Martin Takáč <martin@takac.name>
 */
interface FilePreviewer
{

	/**
	 * @param \Taco\Nette\Forms\Controls\FileUploaded|\Taco\Nette\Forms\Controls\FileCurrent $val
	 */
	function getPreviewControlFor($val): Html;

}
