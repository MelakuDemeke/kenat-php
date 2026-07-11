<?php

declare(strict_types=1);

namespace Kenat\Exceptions;

/**
 * Thrown when a date string provided to the constructor has an invalid format.
 */
class InvalidDateFormatException extends KenatException
{
    public function __construct(public readonly mixed $inputString)
    {
        parent::__construct(sprintf(
            'Invalid date string format: "%s". Expected \'yyyy/mm/dd\' or \'yyyy-mm-dd\'.',
            is_scalar($inputString) ? (string) $inputString : gettype($inputString)
        ));
    }

    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'inputString' => $this->inputString,
        ];
    }
}
