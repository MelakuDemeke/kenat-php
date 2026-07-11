<?php

declare(strict_types=1);

namespace Kenat\Exceptions;

/**
 * Thrown when a function receives an argument of an incorrect type.
 */
class InvalidInputTypeException extends KenatException
{
    public function __construct(
        public readonly string $functionName,
        public readonly string $parameterName,
        public readonly string $expectedType,
        public readonly mixed $receivedValue
    ) {
        parent::__construct(sprintf(
            "Invalid type for parameter '%s' in function '%s'. Expected '%s' but got '%s'.",
            $parameterName,
            $functionName,
            $expectedType,
            get_debug_type($receivedValue)
        ));
    }

    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'functionName' => $this->functionName,
            'parameterName' => $this->parameterName,
            'expectedType' => $this->expectedType,
            'receivedType' => get_debug_type($this->receivedValue),
        ];
    }
}
