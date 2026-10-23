<?php declare(strict_types=1);

/**
 * @copyright Martin Procházka (c) 2020
 * @license   MIT License
 */

namespace JuniWalk\Xml\Iterators;

use DOMDocument;
use JuniWalk\Xml\Exceptions\XmlException;
use DOMElement;
use DOMNode;
use Iterator;
use SimpleXMLElement;
use XMLReader;

use function array_column;
use function array_slice;
use function count;
use function htmlspecialchars;
use function implode;
use function simplexml_import_dom;

/**
 * Streams XMLReader::ELEMENT nodes, optionally filtered by tag name
 *
 * @implements Iterator<int, SimpleXMLElement|null>
 */
class ElementIterator implements Iterator
{
	private const XMLNS_NAMESPACE = 'http://www.w3.org/2000/xmlns/';

	private int $index = 0;
	private bool $started = false;
	private ?string $name;

	/**
	 * Ancestor stack keyed by depth
	 * 
	 * @var array<int, array{name: string, attributes: array<string, string>}>
	 */
	private array $elementStack = [];


	public function __construct(
		private readonly XMLReader $reader,
		?string $name = null,
		private readonly bool $stripNamespaces = true,
		private readonly bool $strictNamespaces = true,
	) {
		if ($name === '*' || $name === '') {
			$name = null;
		}

		$this->name = $name;
	}


	public function getReader(): XMLReader
	{
		return $this->reader;
	}


	public function rewind(): void
	{
		if (!$this->started) {
			$this->started = true;
			$this->moveToNext();
		}

		$this->index = 0;
	}


	public function valid(): bool
	{
		return $this->reader->nodeType === XMLReader::ELEMENT;
	}


	public function current(): ?SimpleXMLElement
	{
		if (!$this->valid()) {
			return null;
		}

		$node = $this->reader->expand(new DOMDocument);

		if (!$node) {
			return null;
		}

		if ($this->stripNamespaces) {
			$node = $this->stripNamespaces($node);
		}

		return simplexml_import_dom($node);
	}


	public function key(): int
	{
		return $this->index;
	}


	public function next(): void
	{
		$this->index++;
		$this->moveToNext();
	}


	public function getNodePath(): string
	{
		return '/'.implode('/', array_column($this->elementStack, 'name'));
	}


	public function getNodeTree(): string
	{
		$buffer = '';

		for ($depth = $this->reader->depth; $depth >= 0; $depth--) {
			if (!isset($this->elementStack[$depth])) {
				continue;
			}

			['name' => $name, 'attributes' => $attributes] = $this->elementStack[$depth];
			$attrString = '';

			foreach ($attributes as $key => $value) {
				$attrString .= ' '.$key.'="'.htmlspecialchars($value, ENT_QUOTES, 'UTF-8').'"';
			}

			$buffer = $buffer === ''
				? '<'.$name.$attrString.'/>'
				: '<'.$name.$attrString.'>'.$buffer.'</'.$name.'>';
		}

		return $buffer;
	}


	private function stripNamespaces(DOMNode $node): DOMNode
	{
		$document = new DOMDocument;
		$document->appendChild($this->cloneStripped($document, $node));

		return $document->documentElement ?? $node;
	}


	private function cloneStripped(DOMDocument $document, DOMNode $node): DOMNode
	{
		if (!$node instanceof DOMElement) {
			return $document->importNode($node, true);
		}

		$clone = $document->createElement($node->localName ?? $node->nodeName);

		foreach ($node->attributes ?? [] as $attribute) {
			if ($attribute->namespaceURI === self::XMLNS_NAMESPACE) {
				continue;
			}

			$name = $attribute->localName ?? $attribute->nodeName;

			if ($this->strictNamespaces && $clone->hasAttribute($name)) {
				throw XmlException::fromAttributeCollision($name, $node->nodeName);
			}

			$clone->setAttribute($name, $attribute->value);
		}

		foreach ($node->childNodes as $child) {
			$clone->appendChild($this->cloneStripped($document, $child));
		}

		return $clone;
	}


	private function moveToNext(): void
	{
		while ($this->reader->read()) {
			if ($this->reader->nodeType !== XMLReader::ELEMENT) {
				continue;
			}

			$this->touchElementStack();

			if ($this->name === null || $this->name === $this->reader->name || ($this->stripNamespaces && $this->name === $this->reader->localName)) {
				return;
			}
		}
	}


	private function touchElementStack(): void
	{
		$depth = $this->reader->depth;
		$attributes = [];

		if ($this->reader->hasAttributes && $this->reader->moveToFirstAttribute()) {
			do {
				if (!$this->stripNamespaces) {
					$attributes[$this->reader->name] = $this->reader->value;
					continue;
				}

				if ($this->reader->namespaceURI === self::XMLNS_NAMESPACE) {
					continue;
				}

				$name = $this->reader->localName;

				if ($this->strictNamespaces && isset($attributes[$name])) {
					throw XmlException::fromAttributeCollision($name, $this->reader->name);
				}

				$attributes[$name] = $this->reader->value;

			} while ($this->reader->moveToNextAttribute());

			$this->reader->moveToElement();
		}

		$this->elementStack[$depth] = ['name' => $this->stripNamespaces ? $this->reader->localName : $this->reader->name, 'attributes' => $attributes];

		if (count($this->elementStack) !== $depth + 1) {
			$this->elementStack = array_slice($this->elementStack, 0, $depth + 1, true);
		}
	}
}
