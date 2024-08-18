<?php
/**
 * Copyright (c) since 2004 Martin Takáč (http://martin.takac.name)
 * @license   https://opensource.org/licenses/MIT MIT
 */

namespace Taco\Nette\Forms\Controls;

use Nette\Utils\Html;


/**
 * We want to represent the uploaded file with a nice icon.
 *
 * @author Martin Takáč <martin@takac.name>
 */
interface FilePreviewer
{

	function getPreviewControlFor(FileUploaded|FileCurrent $val): Html;

}
