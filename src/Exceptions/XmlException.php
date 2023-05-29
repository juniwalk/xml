<?php declare(strict_types=1);

/**
 * @copyright Martin Procházka (c) 2020
 * @license   MIT License
 */

namespace JuniWalk\Xml\Exceptions;

use libXMLError;
use RuntimeException;

class XmlException extends RuntimeException
{
	protected $column;

	public static function fromXmlError(libXMLError $error): self
	{
		$self = new self($error->message, $error->code);
		$self->column = $error->column;
		$self->file = $error->file;
		$self->line = $error->line;

		return $self;
	}
}
