<?php

declare(strict_types=1);

namespace Kenat;

/**
 * Canonical fasting keys, to avoid hardcoded strings in consumer code.
 */
final class FastingKeys
{
    public const ABIY_TSOME = 'ABIY_TSOME';
    public const TSOME_HAWARYAT = 'TSOME_HAWARYAT';
    public const TSOME_NEBIYAT = 'TSOME_NEBIYAT';
    public const NINEVEH = 'NINEVEH';
    public const FILSETA = 'FILSETA';
    public const RAMADAN = 'RAMADAN';
    public const TSOME_DIHENET = 'TSOME_DIHENET';

    private function __construct()
    {
    }
}
