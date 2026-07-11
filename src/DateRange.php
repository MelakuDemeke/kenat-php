<?php

declare(strict_types=1);

namespace Kenat;

use JsonSerializable;

/**
 * A range of Ethiopian dates, e.g. the start and end of a fasting period.
 */
final class DateRange implements JsonSerializable
{
    public function __construct(
        public readonly EthiopianDate $start,
        public readonly EthiopianDate $end
    ) {
    }

    /** @return array{start: array{year:int,month:int,day:int}, end: array{year:int,month:int,day:int}} */
    public function toArray(): array
    {
        return ['start' => $this->start->toArray(), 'end' => $this->end->toArray()];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
