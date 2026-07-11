<?php

declare(strict_types=1);

namespace Kenat\Exceptions;

/**
 * Thrown when a Gregorian date is numerically invalid.
 */
class InvalidGregorianDateException extends KenatException
{
    public function __construct(public readonly mixed $year, public readonly mixed $month, public readonly mixed $day)
    {
        parent::__construct(sprintf('Invalid Gregorian date: %s/%s/%s', self::stringify($year), self::stringify($month), self::stringify($day)));
    }

    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'date' => ['year' => $this->year, 'month' => $this->month, 'day' => $this->day],
            'validRange' => [
                'month' => '1–12',
                'day' => '1–31 (depending on month)',
            ],
        ];
    }

    private static function stringify(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : gettype($value);
    }
}
