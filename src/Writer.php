<?php declare(strict_types=1);

/**
 * @copyright Martin Procházka (c) 2020
 * @license   MIT License
 */

namespace JuniWalk\Xml;

use JuniWalk\Xml\Exceptions\FileHandlingException;
use JuniWalk\Xml\Exceptions\XmlException;
use Latte\Engine;
use Nette\Utils\Html;

class Writer
{
	/** @var string */
	private $templateFile;

	/** @var Engine */
	private $latte;

	/** @var Html */
	private $tag;

	/** @var resource */
	private $stream;

	/** @var string */
	private $interupt = false;

	/** @var string[] */
	private $params;

	/** @var string */
	private $file;


	/**
	 * @param  string  $file
	 * @param  Engine|null  $latte
	 */
	public function __construct(string $file, Engine $latte = null)
	{
		$this->latte = $latte ?? new Engine;
		$this->file = $file;
	}


	/**
	 * @param  string  $templateFile
	 * @param  string[]  $params
	 * @return void
	 */
	public function setTemplateFile(string $templateFile, iterable $params = []): void
	{
		$this->templateFile = $templateFile;
		$this->params = $params;
	}


	/**
	 * @param  string  $tag
	 * @param  string[]  $attributes
	 * @return void
	 * @throws FileHandlingException
	 * @throws XmlException
	 */
	public function open(string $tag, iterable $attributes = []): void
	{
		$this->tag = Html::el($tag)->addAttributes($attributes);

		if (!$this->stream = @fopen($this->file, 'w+')) {
			throw FileHandlingException::fromLastError();
		}

		$this->write('<?xml version="1.0" encoding="utf-8"?>');
		$this->write($this->tag->startTag());
	}


	/**
	 * @param  mixed[]  $items
	 * @param  callable|null  $callback
	 * @return void
	 * @throws XmlException
	 */
	public function items(iterable $items, callable $callback = null): void
	{
		foreach ($items as $item) {
			if ($this->interupt === true) {
				break;
			}

			if ($callback && !$callback($item)) {
				continue;
			}

			$this->write($this->renderItem($item));
		}
	}


	/**
	 * @return void
	 */
	public function enableAsyncInterupt(): void
	{
		pcntl_async_signals(true);
		pcntl_signal(SIGINT, function() {
			$this->interupt = true;
		});
	}


	/**
	 * @return void
	 */
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
	 * @param  mixed  $item
	 * @return string
	 */
	private function renderItem($item): string
	{
		return $this->latte->renderToString(
			$this->templateFile,
			$this->params + [
				'item' => $item,
			]
		);
	}


	/**
	 * @param  string  $content
	 * @param  bool  $newline
	 * @return void
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
}
