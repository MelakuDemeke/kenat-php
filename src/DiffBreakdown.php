<?php

declare(strict_types=1);

namespace Kenat;

use JsonSerializable;

/**
 * A human-friendly breakdown of the distance between two Ethiopian dates.
 */
final class DiffBreakdown implements JsonSerializable
{
    /** @param int $sign Either 1 or -1. */
    public function __construct(
        public readonly int $sign,
        public readonly int $totalDays,
        public readonly ?int $years = null,
        public readonly ?int $months = null,
        public readonly ?int $days = null
    ) {
    }

    /** @return array<string, int|null> */
    public function toArray(): array
    {
        return [
            'sign' => $this->sign,
            'totalDays' => $this->totalDays,
            'years' => $this->years,
            'months' => $this->months,
            'days' => $this->days,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
