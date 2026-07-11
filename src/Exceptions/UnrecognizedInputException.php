<?php

declare(strict_types=1);

namespace Kenat\Exceptions;

/**
 * Thrown when the Kenat constructor receives an input type it cannot handle.
 */
class UnrecognizedInputException extends KenatException
{
    public function __construct(public readonly mixed $input)
    {
        parent::__construct(sprintf('Unrecognized input type for Kenat constructor: %s', get_debug_type($input)));
    }

    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'inputType' => get_debug_type($this->input),
        ];
    }
}
