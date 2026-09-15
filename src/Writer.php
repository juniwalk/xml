<?php declare(strict_types=1);

/**
 * @copyright Martin Procházka (c) 2020
 * @license   MIT License
 */

namespace JuniWalk\Xml;

use JuniWalk\Xml\Exceptions\FileHandlingException;
use JuniWalk\Xml\Exceptions\XmlException;
use JuniWalk\Xml\Exceptions\XmlInteruptException;
use Nette\Utils\Html;
use Latte\Engine as LatteEngine;

/**
 * @phpstan-type Params array<string, mixed>
 * @phpstan-type Handler callable(mixed): bool
 */
class Writer
{
	/** @var Params */
	private array $params = [];
	private bool $interupt = false;
	private Html $tag;

	/** @var resource */
	private $stream;

	/**
	 * @throws FileHandlingException
	 */
	public function __construct(
		private string $file,
		private string $templateFile,
		private LatteEngine $latteEngine = new LatteEngine,
	) {
		if (is_file($templateFile)) {
			return;
		}

		throw new FileHandlingException('Missing template file.');
	}


	public function __destruct()
	{
		$this->close();
	}


	public function isOpen(): bool
	{
		return isset($this->stream) && is_resource($this->stream);	// @phpstan-ignore isset.property
	}


	/**
	 * @param Params $params
	 */
	public function setParams(array $params): void
	{
		$this->params = $params;
	}


	public function enableAsyncInterupt(): void
	{
		if (!function_exists('pcntl_signal')) {
			return;
		}

		pcntl_signal(SIGINT, fn() => $this->interupt = true);
		pcntl_async_signals(true);
	}


	/**
	 * @param  array<string, scalar> $attributes
	 * @throws FileHandlingException
	 * @throws XmlException
	 */
	public function open(string $tag, array $attributes = []): void
	{
		if (!$fp = @fopen($this->file, 'w+')) {
			throw FileHandlingException::fromLastError();
		}

		$this->tag = Html::el($tag)->addAttributes($attributes);
		$this->stream = $fp;

		$this->write('<?xml version="1.0" encoding="utf-8"?>');
		$this->write($this->tag->startTag());
	}


	/**
	 * @param  mixed[] $items
	 * @param  Handler $callback
	 * @throws XmlException
	 */
	public function items(array $items, ?callable $callback = null): void
	{
		foreach ($items as $item) {
			try {
				$this->item($item, $callback);

			} catch (XmlInteruptException) {
				break;
			}
		}
	}


	/**
	 * @param  Handler $callback
	 * @param  Params $params
	 * @throws XmlException
	 * @throws XmlInteruptException
	 */
	public function item(mixed $item, ?callable $callback = null, array $params = []): void
	{
		$callback ??= static fn() => true;

		if ($this->interupt) {
			throw new XmlInteruptException;
		}

		if (!$callback($item)) {
			return;
		}

		$content = $this->renderNode($item, $params);

		if (!trim($content)) {
			return;
		}

		$this->write($content);
	}


	public function close(): void
	{
		if (!$this->isOpen()) {
			return;
		}

		$this->write($this->tag->endTag(), false);

		fclose($this->stream);
		unset($this->stream);
	}


	/**
	 * @throws XmlException
	 */
	private function write(string $content, bool $newline = true): void
	{
		if (!$this->isOpen()) {
			throw new XmlException('XML file is not opened');
		}

		$content .= $newline ? PHP_EOL : null;

		if (fwrite($this->stream, $content) === false) {
			throw new XmlException('Unable to write into XML file');
		}
	}


	/**
	 * @param Params $params
	 */
	private function renderNode(mixed $item, array $params = []): string
	{
		$params['item'] = $item;

		return $this->latteEngine->renderToString(
			$this->templateFile,
			$this->params + $params,
		);
	}
}
