<?php declare(strict_types=1);

/**
 * @copyright Martin Procházka (c) 2020
 * @license   MIT License
 */

namespace JuniWalk\Xml;

use JuniWalk\Utils\Html;
use JuniWalk\Xml\Exceptions\FileHandlingException;
use JuniWalk\Xml\Exceptions\XmlException;
use JuniWalk\Xml\Exceptions\XmlInteruptException;
use Latte\Engine as LatteEngine;

class Writer
{
	private bool $interupt = false;
	private array $params = [];
	private Html $tag;

	/** @var resource */
	private $stream;

	public function __construct(
		private string $file,
		private string $templateFile,
		private LatteEngine $latteEngine = new LatteEngine,
	) { }


	public function __destruct()
	{
		$this->close();
	}


	public function setParams(array $params): void
	{
		$this->params = $params;
	}


	public function enableAsyncInterupt(): void
	{
		pcntl_signal(SIGINT, fn() => $this->interupt = true);
		pcntl_async_signals(true);
	}


	/**
	 * @throws FileHandlingException
	 * @throws XmlException
	 */
	public function open(string $tag, array $attributes = []): void
	{
		$this->tag = Html::el($tag)->addAttributes($attributes);

		if (!$this->stream = @fopen($this->file, 'w+')) {
			throw FileHandlingException::fromLastError();
		}

		$this->write('<?xml version="1.0" encoding="utf-8"?>');
		$this->write($this->tag->startTag());
	}


	/**
	 * @throws XmlException
	 */
	public function items(array $items, callable $callback = null): void
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
	 * @throws XmlException
	 * @throws XmlInteruptException
	 */
	public function item(
		mixed $item,
		callable $callback = null,
		array $params = [],
	): void {
		$callback ??= fn() => true;

		if ($this->interupt) {
			throw new XmlInteruptException;
		}

		if (!$callback($item)) {
			return;
		}

		$content = $this->renderNode($item, $params);
		$this->write($content);
	}


	public function close(): void
	{
		if ($this->stream === null) {
			return;
		}

		$this->write($this->tag->endTag(), false);

		fclose($this->stream);
		$this->stream = null;
	}


	/**
	 * @throws XmlException
	 */
	private function write(string $content, bool $newline = true): void
	{
		if ($this->stream === null) {
			throw new XmlException('XML file is not opened');
		}

		$content .= $newline ? PHP_EOL : null;

		if (fwrite($this->stream, $content) === false) {
			throw new XmlException('Unable to write into XML file');
		}
	}


	private function renderNode(mixed $item, array $params = []): mixed
	{
		$params['item'] = $item;

		if (!isset($this->templateFile)) {
			return $item;
		}

		return $this->latteEngine->renderToString(
			$this->templateFile,
			$this->params + $params,
		);
	}
}
