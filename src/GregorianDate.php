<?php

declare(strict_types=1);

namespace Kenat;

use JsonSerializable;

/**
 * An immutable Gregorian calendar date: { year, month, day }.
 */
final class GregorianDate implements JsonSerializable
{
    public function __construct(
        public readonly int $year,
        public readonly int $month,
        public readonly int $day
    ) {
    }

    /** @param array{year: int, month: int, day: int} $data */
    public static function fromArray(array $data): self
    {
        return new self($data['year'], $data['month'], $data['day']);
    }

    /** @return array{year: int, month: int, day: int} */
    public function toArray(): array
    {
        return ['year' => $this->year, 'month' => $this->month, 'day' => $this->day];
    }

    public function equals(self $other): bool
    {
        return $this->year === $other->year
            && $this->month === $other->month
            && $this->day === $other->day;
    }

    /** @return array{year: int, month: int, day: int} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
