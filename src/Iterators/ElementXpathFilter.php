<?php declare(strict_types=1);

/**
 * @copyright Martin Procházka (c) 2020
 * @license   MIT License
 */

namespace JuniWalk\Xml\Iterators;

use FilterIterator;
use SimpleXMLElement;

use function array_key_last;
use function simplexml_load_string;

/**
 * Filters an ElementIterator by matching the ancestor path of the current node
 * against a Xpath expression, eg. //item[@id]
 *
 * @extends FilterIterator<int, SimpleXMLElement|null, ElementIterator>
 */
class ElementXpathFilter extends FilterIterator
{
	public function __construct(
		private readonly ElementIterator $iterator,
		private readonly string $expression,
	) {
		parent::__construct($iterator);
	}


	public function getInnerIterator(): ElementIterator
	{
		return $this->iterator;
	}


	public function accept(): bool
	{
		if ('' === $tree = $this->iterator->getNodeTree()) {
			return false;
		}

		if (!$xml = @simplexml_load_string($tree)) {
			return false;
		}

		if (!$result = $xml->xpath($this->expression)) {
			return false;
		}

		$last = $result[array_key_last($result)];

		// Only accept the deepest match so ancestors of the target node are skipped
		return $last->children()->count() === 0;
	}
}
