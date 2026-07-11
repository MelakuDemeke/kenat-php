<?php

declare(strict_types=1);

namespace Kenat;

use JsonSerializable;

/**
 * A single holiday/feast occurrence, fixed or movable.
 */
final class Holiday implements JsonSerializable
{
    /**
     * @param string[] $tags
     */
    public function __construct(
        public readonly string $key,
        public readonly array $tags,
        public readonly bool $movable,
        public readonly ?string $name,
        public readonly ?string $description,
        public readonly EthiopianDate $ethiopian,
        public readonly ?GregorianDate $gregorian = null
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'tags' => $this->tags,
            'movable' => $this->movable,
            'name' => $this->name,
            'description' => $this->description,
            'ethiopian' => $this->ethiopian->toArray(),
            'gregorian' => $this->gregorian?->toArray(),
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
