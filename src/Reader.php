<?php declare(strict_types=1);

/**
 * @copyright Martin Procházka (c) 2020
 * @license   MIT License
 */

namespace JuniWalk\Xml;

use DOMNode;
use JuniWalk\Xml\Exceptions\FileHandlingException;
use JuniWalk\Xml\Exceptions\XmlException;
use JuniWalk\Xml\Iterators\ElementIterator;
use JuniWalk\Xml\Iterators\ElementXpathFilter;
use XmlReader;

use function libxml_clear_errors;
use function libxml_get_last_error;
use function libxml_use_internal_errors;

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


	public function stream(?string $node = null, bool $strictNamespaces = true): ElementIterator
	{
		return new ElementIterator($this, $node, $strictNamespaces);
	}


	public function xpath(string $xpath, bool $strictNamespaces = true): ElementXpathFilter
	{
		return new ElementXpathFilter($this->stream(null, $strictNamespaces), $xpath);
	}


	/**
	 * @throws XmlException
	 */
	private function checkForErrors(): void
	{
		if ($lastError = libxml_get_last_error()) {
			throw XmlException::fromXmlError($lastError);
		}

		libxml_clear_errors();
	}
}
