<?php

declare(strict_types=1);

namespace Kenat;

/**
 * Stable tag strings usable to filter holiday/fasting queries.
 */
final class HolidayTags
{
    public const PUBLIC = 'public';
    public const RELIGIOUS = 'religious';
    public const CHRISTIAN = 'christian';
    public const MUSLIM = 'muslim';
    public const STATE = 'state';
    public const CULTURAL = 'cultural';
    public const OTHER = 'other';

    private function __construct()
    {
    }
}
