<?php declare(strict_types=1);

/**
 * @copyright Martin Procházka (c) 2020
 * @license   MIT License
 */

namespace JuniWalk\Xml\Exceptions;

final class FileHandlingException extends XmlException
{
	public static function fromLastError(): self
	{
		if (!$error = error_get_last()) {
			return new self('No last error detected.');
		}

		$self = new self($error['message'], $error['type']);
		$self->file = $error['file'];
		$self->line = $error['line'];

		return $self;
	}
}
