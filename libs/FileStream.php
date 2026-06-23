<?php declare(strict_types = 1);

/**
 * Copyright (c) since 2004 Martin Takáč (http://martin.takac.name)
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace Taco\Nette\Forms\Controls;

use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Throwable;


class FileStream implements StreamInterface
{

	/**
	 * @var resource|null
	 */
	private $handle;

	private string $path;

	function __construct(string $path)
	{
		$this->path = $path;
		$handle = fopen($path, 'r');
		if ($handle === false) {
			throw new RuntimeException("Unable to open file '{$path}'.");
		}
		$this->handle = $handle;
	}



	function close(): void
	{
		if ($this->handle !== null) {
			fclose($this->handle);
			$this->handle = null;
		}
	}



	function detach()
	{
		$handle = $this->handle;
		$this->handle = null;
		return $handle;
	}



	function getSize(): ?int
	{
		$size = filesize($this->path);
		return $size === false
			? null
			: $size;
	}



	function tell(): int
	{
		$pos = ftell($this->handle());
		if ($pos === false) {
			throw new RuntimeException('Unable to determine stream position.');
		}
		return $pos;
	}



	function eof(): bool
	{
		return feof($this->handle());
	}



	function isSeekable(): bool
	{
		return true;
	}



	function seek(int $offset, int $whence = SEEK_SET): void
	{
		if (fseek($this->handle(), $offset, $whence) === -1) {
			throw new RuntimeException('Unable to seek in stream.');
		}
	}



	function rewind(): void
	{
		if (!rewind($this->handle())) {
			throw new RuntimeException('Unable to rewind stream.');
		}
	}



	function isWritable(): bool
	{
		return false;
	}



	function write(string $string): int
	{
		throw new RuntimeException('Stream je pouze pro čtení.');
	}



	function isReadable(): bool
	{
		return true;
	}



	function read(int $length): string
	{
		if ($length < 1) {
			return '';
		}
		$data = fread($this->handle(), $length);
		if ($data === false) {
			throw new RuntimeException('Unable to read from stream.');
		}
		return $data;
	}



	function getContents(): string
	{
		$data = stream_get_contents($this->handle());
		if ($data === false) {
			throw new RuntimeException('Unable to read from stream.');
		}
		return $data;
	}



	function getMetadata(?string $key = null): mixed
	{
		$meta = stream_get_meta_data($this->handle());
		return $key === null ? $meta : ($meta[$key] ?? null);
	}



	/**
	 * @return resource
	 */
	private function handle()
	{
		if ($this->handle === null) {
			throw new RuntimeException('Stream has been detached.');
		}
		return $this->handle;
	}



	function __toString(): string
	{
		try {
			$this->rewind();
			return $this->getContents();
		}
		catch (Throwable $e) {
			return '';
		}
	}

}
