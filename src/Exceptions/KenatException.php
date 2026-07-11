<?php

declare(strict_types=1);

namespace Kenat\Exceptions;

use JsonSerializable;
use RuntimeException;

/**
 * Base class for all custom exceptions in the Kenat library.
 */
class KenatException extends RuntimeException implements JsonSerializable
{
    public function __construct(string $message = '')
    {
        parent::__construct($message);
    }

    /**
     * Provides a serializable representation of the error.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->shortName(),
            'message' => $this->getMessage(),
        ];
    }

    /**
     * The exception's class name without its namespace, mirroring the
     * `this.constructor.name` used by the JS/TS KenatError.toJSON().
     */
    protected function shortName(): string
    {
        $parts = explode('\\', static::class);
        return end($parts);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
