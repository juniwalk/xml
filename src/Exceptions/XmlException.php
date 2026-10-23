<?php declare(strict_types=1);

/**
 * @copyright Martin Procházka (c) 2020
 * @license   MIT License
 */

namespace JuniWalk\Xml\Exceptions;

use LibXMLError ;
use RuntimeException;

class XmlException extends RuntimeException
{
	protected int $column;

	public static function fromXmlError(LibXMLError $error): self
	{
		$self = new self($error->message, $error->code);
		$self->column = $error->column;
		$self->file = $error->file;
		$self->line = $error->line;

		return $self;
	}


	public static function fromAttributeCollision(string $attribute, string $element): self
	{
		return new self('Attribute "'.$attribute.'" of element <'.$element.'> collides after namespace stripping.');
	}
}
