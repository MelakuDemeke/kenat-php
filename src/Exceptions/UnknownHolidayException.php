<?php

declare(strict_types=1);

namespace Kenat\Exceptions;

/**
 * Thrown when an unknown holiday key is used.
 */
class UnknownHolidayException extends KenatException
{
    public function __construct(public readonly mixed $holidayKey)
    {
        parent::__construct(sprintf(
            'Unknown movable holiday key: "%s"',
            is_scalar($holidayKey) ? (string) $holidayKey : gettype($holidayKey)
        ));
    }

    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'holidayKey' => $this->holidayKey,
        ];
    }
}
