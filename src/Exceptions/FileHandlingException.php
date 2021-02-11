<?php declare(strict_types=1);

/**
 * @copyright Martin Procházka (c) 2020
 * @license   MIT License
 */

namespace JuniWalk\Xml\Exceptions;

final class FileHandlingException extends XmlException
{
	/**
	 * @return self
	 */
	public static function fromLastError(): self
	{
		$error = error_get_last();

		$ex = new self($error['message'], $error['type']);
		$ex->file = $error['file'];
		$ex->line = $error['line'];

		return $ex;
	}
}
