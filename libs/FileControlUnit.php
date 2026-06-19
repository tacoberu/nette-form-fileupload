<?php declare(strict_types = 1);

/**
 * Copyright (c) since 2004 Martin Takáč (http://martin.takac.name)
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace Taco\Nette\Forms\Controls;

use Nette\Forms\Form;
use Nette\Forms;
use Nette\Http\FileUpload;
use Nette\Http\IRequest;
use Nette\Application\UI\Presenter;
use Nette\Application\UI\BadSignalException;
use Nette\InvalidStateException;


trait FileControlUnit
{

	/**
	 * A repository holding uploaded files before they are actually saved.
	 * By default it's just a temp directory, see UploadStoreTemp
	 * @readonly
	 */
	private UploadStore $store;

	private ?FilePreviewer $previewer = Null;

	private string $prefix = "taco-filecontrol";

	/**
	 * By setting the previewer, uploaded files will be represented by their respective previews.
	 */
	function setPreviewer(FilePreviewer $var): self
	{
		$this->previewer = $var;
		return $this;
	}



	/**
	 * Have been all files successfully uploaded?
	 */
	function isOk(): bool
	{
		return True;
	}



	/**
	 * Has been any file uploaded?
	 */
	function isFilled(): bool
	{
		return !empty($this->value);
	}



	/**
	 * Delete the transaction directory and all its contents.
	 */
	function destroyStore(): void
	{
		$this->store->destroy();
	}



	/**
	 * Overrides addRule to redirect Nette's file validators (which type-hint UploadControl)
	 * to our own equivalents in Utils that accept any Control.
	 *
	 * @param callable|string $validator
	 * @param string|null $errorMessage
	 * @param mixed $arg
	 * @return static
	 */
	function addRule($validator, $errorMessage = null, $arg = null): self
	{
		if ($validator === Form::Image) {
			$this->control->accept = implode(', ', Forms\Helpers::getSupportedImages());
			$this->getRules()->removeRule([Utils::class, 'validateImage']);
			return parent::addRule(
				[Utils::class, 'validateImage'],
				$errorMessage ?? Forms\Validator::$messages[Form::Image],
				$arg
			);
		}
		elseif ($validator === Form::MimeType) {
			$this->control->accept = implode(', ', (array) $arg);
			$this->getRules()->removeRule([Utils::class, 'validateMimeType']);
			return parent::addRule(
				[Utils::class, 'validateMimeType'],
				$errorMessage ?? Forms\Validator::$messages[Form::MimeType],
				$arg
			);
		}
		elseif ($validator === Form::MaxFileSize) {
			if ($arg > ($ini = Forms\Helpers::iniGetSize('upload_max_filesize'))) {
				trigger_error("Value of MaxFileSize ($arg) is greater than value of directive upload_max_filesize ($ini).", E_USER_WARNING);
			}
			$this->getRules()->removeRule([Utils::class, 'validateFileSize']);
			return parent::addRule(
				[Utils::class, 'validateFileSize'],
				$errorMessage ?? Forms\Validator::$messages[Form::MaxFileSize],
				$arg
			);
		}
		return parent::addRule($validator, $errorMessage, $arg);
	}



	public function signalReceived(string $signal): void
	{
		switch ($signal) {
			case 'upload':
				$this->handleUpload();
				break;
			default:
				throw new BadSignalException("Signal '$signal' not supported by " . static::class . '.');
		}
	}



	/**
	 * Receives a single file (or chunk) via AJAX POST, stores it in the transaction, and returns JSON.
	 *
	 * The client should pass the current transaction ID in the 'transaction' POST field
	 * so that multiple consecutive uploads land in the same temporary directory.
	 */
	private function handleUpload(): void
	{
		$presenter = $this->getPresenter();
		$request = $presenter->getHttpRequest();

		$transactionId = $request->getPost('transaction');
		if ($transactionId !== null) {
			$this->store->setId((int) $transactionId);
		}

		// Chunked upload: large file split by the client into pieces.
		// Each request carries chunkIndex/chunkTotal; the store assembles them.
		$chunkIndex = $request->getPost('chunkIndex');
		$chunkTotal = $request->getPost('chunkTotal');
		if ($chunkIndex !== null && $chunkTotal !== null) {
			$this->processChunk(
				$presenter,
				(int) $chunkIndex,
				(int) $chunkTotal,
				(string) $request->getPost('chunkId'),
				$this->getUploadedFile($request, 'file')
			);
			return;
		}

		// Single upload: small file sent in one request (no chunkIndex present).
		$file = $this->getUploadedFile($request, 'file');
		if ($file === null || !$file->isOk()) {
			$presenter->sendJson(['error' => $this->detectFileError($file)]);
		}
		$uploaded = $this->store->append($file);
		$presenter->sendJson([
			'file' => Utils::serializeFile($uploaded),
			'transaction' => $this->store->getId(),
			'name' => $uploaded->getName(),
			'preview' => $this->renderPreviewHtml($uploaded),
		]);
	}



	private function processChunk(
		Presenter $presenter,
		int $chunkIndex,
		int $chunkTotal,
		string $chunkId,
		?FileUpload $chunk
	): void
	{
		if ($chunk === null || !$chunk->isOk()) {
			$presenter->sendJson(['error' => $this->detectFileError($chunk)]);
		}

		$uploaded = $this->store->appendChunk($chunk, $chunkId, $chunkIndex, $chunkTotal);

		if ($uploaded === null) {
			$presenter->sendJson(['progress' => ($chunkIndex + 1) / $chunkTotal]);
		}

		$presenter->sendJson([
			'file' => Utils::serializeFile($uploaded),
			'transaction' => $this->store->getId(),
			'name' => $uploaded->getName(),
			'preview' => $this->renderPreviewHtml($uploaded),
		]);
	}



	private function renderPreviewHtml(FileUploaded $uploaded): string
	{
		if ($this->previewer === null) {
			return '';
		}
		return (string) $this->previewer->getPreviewControlFor($this->store, $this, $uploaded);
	}



	private function detectFileError(?FileUpload $file): string
	{
		if ($file !== null) {
			return Utils::formatError($file);
		}
		$contentLength = (int) (filter_input(INPUT_SERVER, 'CONTENT_LENGTH') ?? 0);
		$postMaxSize = Forms\Helpers::iniGetSize('post_max_size');
		return $postMaxSize > 0 && $contentLength > $postMaxSize
			? sprintf(Forms\Validator::$messages[Form::MaxFileSize], $postMaxSize)
			: 'No file received';
	}



	/**
	 * Generates a signal URL for this control.
	 * Cannot use UI\Component::link() path (FileControl/MultiFileControl is not UI\Component, link generator
	 * would reject it). Instead builds 'path-signal!' which bypasses sub-component lookup
	 * and lets the Presenter dispatch via the 'do' parameter directly.
	 */
	private function link(string $signal): string
	{
		$signal = rtrim(ltrim($signal, ':'), '!');
		$presenter = $this->getPresenter();
		return $presenter->link($this->lookupPath(Presenter::class) . '-' . $signal . '!');
	}



	/**
	 * lookup() is only typed to return IComponent; narrow it to Presenter here
	 * so callers can use Presenter-specific methods (getHttpRequest, sendJson, link).
	 */
	private function getPresenter(): Presenter
	{
		$presenter = $this->lookup(Presenter::class, false);
		if (!$presenter instanceof Presenter) {
			throw new InvalidStateException(static::class . ' is not attached to a Presenter.');
		}
		return $presenter;
	}



	/**
	 * IRequest::getFile() is typed to also allow an array (tree of files for array-style
	 * input names). Our control always submits a single file under a plain key, so anything
	 * other than a FileUpload instance is treated as no file received.
	 */
	private function getUploadedFile(IRequest $request, string $key): ?FileUpload
	{
		$file = $request->getFile($key);
		return $file instanceof FileUpload
			? $file
			: null;
	}



	private function formatClass(?string $suffix): string
	{
		return $suffix
			? "{$this->prefix}-{$suffix}"
			: $this->prefix;
	}

}
