<?php declare(strict_types=1);

/**
 * @copyright Martin Prochazka (c) 2026
 * @license   Private License
 */

namespace JuniWalk\Tests;

require __DIR__.'/../bootstrap.php';

use JuniWalk\Xml\Exceptions\XmlException;
use JuniWalk\Xml\Reader;
use SimpleXMLElement;
use Tester\Assert;
use Tester\TestCase;
use function file_exists;

final class ReaderIterationTest extends TestCase
{
	public function testStreamIteratorYieldsElementNodes(): void
	{
		$file = __DIR__.'/fixtures/items-stream.xml';
		Assert::true(file_exists($file));

		$reader = new Reader($file);
		$ids = [];
		$titles = [];

		foreach ($reader->stream('item') as $item) {
			Assert::type(SimpleXMLElement::class, $item);
			$ids[] = (string) $item['id'];
			$titles[] = (string) $item->title;
		}

		Assert::same(['1', '2'], $ids);
		Assert::same(['First', 'Second'], $titles);
	}


	public function testXpathIteratorFiltersMatchingNodes(): void
	{
		$file = __DIR__.'/fixtures/items-xpath.xml';
		Assert::true(file_exists($file));

		$reader = new Reader($file);
		$ids = [];

		foreach ($reader->xpath('//item') as $item) {
			Assert::type(SimpleXMLElement::class, $item);
			$ids[] = (string) $item['id'];
		}

		Assert::same(['1', '2', '3'], $ids);
	}


	public function testNamespacesAreStripped(): void
	{
		$file = __DIR__.'/fixtures/items-namespaced.xml';
		Assert::true(file_exists($file));

		$ids = [];
		$titles = [];

		foreach ((new Reader($file))->stream('item') as $item) {
			Assert::type(SimpleXMLElement::class, $item);
			$ids[] = (string) $item['id'];
			$titles[] = (string) $item->title;
		}

		Assert::same(['1', '2'], $ids);
		Assert::same(['First', 'Second'], $titles);

		$ids = [];

		foreach ((new Reader($file))->xpath('//item[@id]') as $item) {
			$ids[] = (string) $item['id'];
		}

		Assert::same(['1', '2'], $ids);
	}


	public function testAttributeCollisionThrowsUnlessSuppressed(): void
	{
		$file = __DIR__.'/fixtures/items-collision.xml';

		Assert::exception(function () use ($file) {
			foreach ((new Reader($file))->stream('item') as $item);
		}, XmlException::class);

		Assert::exception(function () use ($file) {
			foreach ((new Reader($file))->xpath('//item') as $item);
		}, XmlException::class);

		$ids = [];

		foreach ((new Reader($file))->stream('item', true, false) as $item) {
			$ids[] = (string) $item['id'];
		}

		Assert::same(['2'], $ids);
	}


	public function testStrippingCanBeDisabled(): void
	{
		$file = __DIR__.'/fixtures/items-stream.xml';
		$ids = [];

		foreach ((new Reader($file))->stream('item', false) as $item) {
			$ids[] = (string) $item['id'];
		}

		foreach ((new Reader($file))->xpath('//item[@id="2"]', false) as $item) {
			$ids[] = (string) $item['id'];
		}

		Assert::same(['1', '2', '2'], $ids);

		$names = [];

		foreach ((new Reader(__DIR__.'/fixtures/items-namespaced.xml'))->stream('feed:item', false) as $item) {
			$names[] = $item->getName();
		}

		Assert::same(['item'], $names);
	}
}

(new ReaderIterationTest())->run();
