<?php

declare(strict_types=1);

namespace Kenat;

use Kenat\Exceptions\GeezConverterException;

/**
 * Converts Arabic numerals to their equivalent Ethiopic (Ge'ez) numerals, and back.
 * Supports numbers from 1 up to 99999999.
 *
 * Example:
 *   GeezConverter::toGeez(1);     // '፩'
 *   GeezConverter::toGeez(30);    // '፴'
 *   GeezConverter::toGeez(123);   // '፻፳፫'
 *   GeezConverter::toGeez(10000); // '፼'
 *
 * Ported from kenat/src/geezConverter.ts.
 */
final class GeezConverter
{
    private const ONES = ['', '፩', '፪', '፫', '፬', '፭', '፮', '፯', '፰', '፱'];
    private const TENS = ['', '፲', '፳', '፴', '፵', '፶', '፷', '፸', '፹', '፺'];
    private const HUNDRED = '፻';
    private const TEN_THOUSAND = '፼';

    private function __construct()
    {
    }

    /**
     * Converts a non-negative integer to an Ethiopic numeral string.
     *
     * @param int|string $input The number to convert (positive integer only).
     * @throws GeezConverterException If input is not a valid non-negative integer.
     */
    public static function toGeez(int|string $input): string
    {
        if (!is_int($input) && !is_string($input)) {
            throw new GeezConverterException('Input must be a number or a string.');
        }

        if (is_string($input) && !is_numeric($input)) {
            throw new GeezConverterException('Input must be a non-negative integer.');
        }

        $numValue = (float) $input;

        if (is_nan($numValue) || floor($numValue) !== $numValue || $numValue < 0) {
            throw new GeezConverterException('Input must be a non-negative integer.');
        }

        $num = (int) $numValue;

        if ($num === 0) {
            return '0'; // Ge'ez has no zero, but useful for modern contexts.
        }

        if ($num < 100) {
            return self::convertBelow100($num);
        }

        if ($num === 100) {
            return self::HUNDRED;
        }

        if ($num < 10000) {
            $hundreds = intdiv($num, 100);
            $remainder = $num % 100;
            // For numbers like 101, it's ፻፩, not ፩፻፩. If the hundred part is 1, don't add a prefix.
            $hundredPart = ($hundreds > 1 ? self::convertBelow100($hundreds) : '') . self::HUNDRED;
            return $hundredPart . self::convertBelow100($remainder);
        }

        // For numbers >= 10000, use recursion.
        $tenThousandPart = intdiv($num, 10000);
        $remainder = $num % 10000;

        // If the ten-thousand part is 1, no prefix is needed (e.g., ፼, not ፩፼).
        $tenThousandGeez = ($tenThousandPart > 1 ? self::toGeez($tenThousandPart) : '') . self::TEN_THOUSAND;

        return $tenThousandGeez . ($remainder > 0 ? self::toGeez($remainder) : '');
    }

    /**
     * Converts a Ge'ez numeral string to its Arabic numeral equivalent.
     *
     * @throws GeezConverterException If the input is not a valid Ge'ez numeral string.
     */
    public static function toArabic(string $geezStr): int
    {
        if (trim($geezStr) === '') {
            return 0;
        }

        $reverseMap = [];
        foreach (self::ONES as $i => $char) {
            if ($char !== '') {
                $reverseMap[$char] = $i;
            }
        }
        foreach (self::TENS as $i => $char) {
            if ($char !== '') {
                $reverseMap[$char] = $i * 10;
            }
        }
        $reverseMap[self::HUNDRED] = 100;
        $reverseMap[self::TEN_THOUSAND] = 10000;

        $total = 0;
        $currentNumber = 0;

        $chars = mb_str_split($geezStr);
        foreach ($chars as $char) {
            if (!array_key_exists($char, $reverseMap)) {
                throw new GeezConverterException("Unknown Ge'ez numeral: {$char}");
            }
            $value = $reverseMap[$char];

            if ($value === 100 || $value === 10000) {
                // If currentNumber is 0, it implies a standalone ፻ or ፼, treat it as 1 * multiplier.
                $currentNumber = ($currentNumber ?: 1) * $value;

                // ፼ acts as a separator for large numbers. Add the completed segment to the total.
                if ($value === 10000) {
                    $total += $currentNumber;
                    $currentNumber = 0;
                }
            } else {
                $currentNumber += $value;
            }
        }

        $total += $currentNumber;
        return $total;
    }

    private static function convertBelow100(int $n): string
    {
        if ($n <= 0) {
            return '';
        }
        $tensDigit = intdiv($n, 10);
        $onesDigit = $n % 10;
        return self::TENS[$tensDigit] . self::ONES[$onesDigit];
    }
}
