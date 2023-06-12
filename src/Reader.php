<?php declare(strict_types=1);

/**
 * @copyright Martin Procházka (c) 2020
 * @license   MIT License
 */

namespace JuniWalk\Xml;

use JuniWalk\Xml\Exceptions\FileHandlingException;
use JuniWalk\Xml\Exceptions\XmlException;
use DOMNode;
use Nette\Utils\Strings;
use XMLElementIterator;
use XMLElementXpathFilter;
use XmlReader;

final class Reader extends XmlReader
{
	/** @var string */
	private $file;


	/**
	 * @param  string  $file
	 * @throws FileHandlingException
	 */
	public function __construct(string $file)
	{
		$this->file = $file;

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
	#[\ReturnTypeWillChange]
	public function expand($baseNode = null)
	{
		$result = @parent::expand($baseNode);
		$this->checkForErrors();

		return $result;
	}


	/**
	 * @param  string|null  $node
	 * @return iterable
	 */
	public function stream(string $node = null): iterable
	{
		return new XMLElementIterator($this, $node);
	}


	/**
	 * @param  string  $xpath
	 * @return iterable
	 */
	public function xpath(string $xpath): iterable
	{
		return new XMLElementXpathFilter($this->stream(), $xpath);
	}


	/**
	 * @param  string  $node
	 * @return string[]
	 */
	public function vanilla(string $node): iterable
	{
		foreach ($this->stream($node) as $item) {
			yield $this->createArray($item->expand());
		}
	}


	/**
	 * @param  string  $node
	 * @return string[]
	 */
	public function vanillaXpath(string $xpath): iterable
	{
		foreach ($this->xpath($xpath) as $item) {
			yield $this->createArray($item->expand());
		}
	}


	/**
	 * @param  DOMNode  $node
	 * @return array|string
	 */
	private function createArray(DOMNode $node)
	{
		$output = [];

		switch ($node->nodeType) {
			case XML_CDATA_SECTION_NODE:
			case XML_TEXT_NODE:
				$output = Strings::trim($node->textContent);
				break;

			case XML_ELEMENT_NODE:
				foreach ($node->childNodes as $child) {
					$value = $this->createArray($child);

					if (isset($child->tagName) && $key = Strings::lower($child->tagName)) {
						$output[$key][] = $value;

					} elseif ($value || $value === '0') {
						$output = (string) $value;
					}
				}

				if ($node->attributes->length && !is_array($output)) {
					$output = ['@content' => $output];
				}

				if (is_array($output)) {
					foreach ($node->attributes as $key => $value) {
						$output['@attributes'][$key] = (string) $value->value;
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
