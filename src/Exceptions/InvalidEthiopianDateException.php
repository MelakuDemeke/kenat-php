<?php

declare(strict_types=1);

namespace Kenat\Exceptions;

/**
 * Thrown when an Ethiopian date is numerically invalid (e.g., month 14).
 */
class InvalidEthiopianDateException extends KenatException
{
    /** @param array<string, mixed> $date */
    public function __construct(public readonly mixed $year, public readonly mixed $month, public readonly mixed $day)
    {
        parent::__construct(sprintf('Invalid Ethiopian date: %s/%s/%s', self::stringify($year), self::stringify($month), self::stringify($day)));
    }

    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'date' => ['year' => $this->year, 'month' => $this->month, 'day' => $this->day],
            'validRange' => [
                'month' => '1–13',
                'day' => '1–30 (or 5/6 for the 13th month)',
            ],
        ];
    }

    private static function stringify(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : gettype($value);
    }
}
