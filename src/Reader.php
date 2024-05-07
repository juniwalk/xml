<?php declare(strict_types=1);

/**
 * @copyright Martin Procházka (c) 2020
 * @license   MIT License
 */

namespace JuniWalk\Xml;

use DOMNode;
use JuniWalk\Xml\Exceptions\FileHandlingException;
use JuniWalk\Xml\Exceptions\XmlException;
use XMLElementIterator;
use XMLElementXpathFilter;
use XmlReader;

final class Reader extends XmlReader
{
	/**
	 * @throws FileHandlingException
	 */
	public function __construct(string $file)
	{
		if (!$this->open($file, null, LIBXML_PARSEHUGE|LIBXML_COMPACT|LIBXML_NOCDATA|LIBXML_BIGLINES)) {
			throw new FileHandlingException('Unable to read '.$file);
		}

		libxml_use_internal_errors(true);
		libxml_clear_errors();
	}


	/**
	 * @throws XmlException
	 */
	public function read(): bool
	{
		$result = @parent::read();
		$this->checkForErrors();

		return $result;
	}


	/**
	 * @throws XmlException
	 */
	public function expand(?DOMNode $baseNode = null): DOMNode|false
	{
		$result = @parent::expand($baseNode);
		$this->checkForErrors();

		return $result;
	}


	public function stream(?string $node = null): XMLElementIterator
	{
		return new XMLElementIterator($this, $node);
	}


	public function xpath(string $xpath): XMLElementXpathFilter
	{
		return new XMLElementXpathFilter($this->stream(), $xpath);
	}


	/**
	 * @return array<int, string|array<string, string>>
	 */
	public function vanilla(string $nodeName): iterable
	{
		foreach ($this->stream($nodeName) as $item) {
			if (!$node = $item?->expand()) {
				continue;
			}

			yield $this->parseNode($node);
		}
	}


	/**
	 * @return array<int, string|array<string, string>>
	 */
	public function vanillaXpath(string $xpath): iterable
	{
		foreach ($this->xpath($xpath) as $item) {
			/** @var XmlReader $item */
			if (!$node = $item->expand()) {
				continue;
			}

			yield $this->parseNode($node);
		}
	}


	/**
	 * @return string|array<string, string>
	 */
	private function parseNode(DOMNode $node): string|array
	{
		$output = [];

		switch ($node->nodeType) {
			case XML_CDATA_SECTION_NODE:
			case XML_TEXT_NODE:
				$output = trim($node->textContent);
				break;

			case XML_ELEMENT_NODE:
				foreach ($node->childNodes as $child) {
					$value = $this->parseNode($child);

					if (isset($child->tagName) && $key = mb_strtolower($child->tagName)) {
						$output[$key][] = $value;

					} elseif ($value || $value === '0') {
						$output = (string) $value;	// @phpstan-ignore-line
					}
				}

				if ($node->attributes?->length && !is_array($output)) {
					$output = ['@content' => $output];
				}

				if (is_array($output)) {
					foreach ($node->attributes ?? [] as $key => $value) {
						/** @var DOMNode $value */
						$output['@attributes'][$key] = (string) $value->nodeValue;
					}

					foreach ($output as $key => $value) {
						if (!is_array($value) || sizeof($value) > 1 || !isset($value[0])) {
							continue;
						}

						$output[$key] = $value[0];
					}
				}

				break;
		}

		return $output;
	}


	private function checkForErrors(): void
	{
		if ($lastError = libxml_get_last_error()) {
			throw XmlException::fromXmlError($lastError);
		}

		libxml_clear_errors();
	}
}
