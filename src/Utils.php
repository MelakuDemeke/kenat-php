<?php

declare(strict_types=1);

namespace Kenat;

use Kenat\Exceptions\InvalidInputTypeException;

/**
 * Low-level validation and pure date-math helpers shared across the codebase.
 *
 * Ported from kenat/src/utils.ts.
 */
final class Utils
{
    private function __construct()
    {
    }

    /**
     * Validates that all provided date parts are numbers (int or float, non-NaN).
     *
     * @param array<string, mixed> $dateParts
     * @throws InvalidInputTypeException if any value is not numeric.
     */
    public static function validateNumericInputs(string $funcName, array $dateParts): void
    {
        foreach ($dateParts as $name => $value) {
            if (!is_int($value) && !is_float($value)) {
                throw new InvalidInputTypeException($funcName, $name, 'number', $value);
            }
            if (is_float($value) && is_nan($value)) {
                throw new InvalidInputTypeException($funcName, $name, 'number', $value);
            }
        }
    }

    /**
     * Validates that the input is a valid Ethiopian date array with numeric year/month/day.
     *
     * @throws InvalidInputTypeException if the object is invalid.
     */
    public static function validateEthiopianDateObject(mixed $dateObj, string $funcName, string $paramName): void
    {
        if ($dateObj instanceof EthiopianDate) {
            return;
        }

        if (!is_array($dateObj)) {
            throw new InvalidInputTypeException($funcName, $paramName, 'array', $dateObj);
        }

        self::validateNumericInputs($funcName, [
            "{$paramName}.year" => $dateObj['year'] ?? null,
            "{$paramName}.month" => $dateObj['month'] ?? null,
            "{$paramName}.day" => $dateObj['day'] ?? null,
        ]);
    }

    /**
     * Validates that the input is a valid Ethiopian time array/object.
     *
     * @throws InvalidInputTypeException if the object is invalid.
     */
    public static function validateEthiopianTimeObject(mixed $timeObj, string $funcName, string $paramName): void
    {
        if (!is_array($timeObj)) {
            throw new InvalidInputTypeException($funcName, $paramName, 'array', $timeObj);
        }

        $period = $timeObj['period'] ?? null;
        if ($period !== 'day' && $period !== 'night') {
            throw new InvalidInputTypeException($funcName, "{$paramName}.period", "'day' or 'night'", $period);
        }

        self::validateNumericInputs($funcName, [
            "{$paramName}.hour" => $timeObj['hour'] ?? null,
            "{$paramName}.minute" => $timeObj['minute'] ?? null,
        ]);
    }

    /**
     * Calculates the day of the year (1-based) for a given Gregorian date.
     */
    public static function dayOfYear(int $year, int $month, int $day): int
    {
        $monthLengths = [31, self::isGregorianLeapYear($year) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        $doy = 0;
        for ($i = 0; $i < $month - 1; $i++) {
            $doy += $monthLengths[$i];
        }
        return $doy + $day;
    }

    /**
     * Converts a day-of-year to Gregorian month and day.
     *
     * @return array{month: int, day: int}
     */
    public static function monthDayFromDayOfYear(int $year, int $dayOfYear): array
    {
        $monthLengths = [31, self::isGregorianLeapYear($year) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        $month = 1;
        while ($dayOfYear > $monthLengths[$month - 1]) {
            $dayOfYear -= $monthLengths[$month - 1];
            $month++;
        }
        return ['month' => $month, 'day' => $dayOfYear];
    }

    /**
     * Gregorian leap years occur every 4 years, except centuries not divisible by 400.
     */
    public static function isGregorianLeapYear(int $year): bool
    {
        return ($year % 4 === 0 && $year % 100 !== 0) || ($year % 400 === 0);
    }

    /**
     * Ethiopian leap years occur every 4 years, when year modulo 4 equals 3.
     */
    public static function isEthiopianLeapYear(int $year): bool
    {
        return self::mod($year, 4) === 3;
    }

    /**
     * Returns the number of days in the given Ethiopian month and year.
     */
    public static function getEthiopianDaysInMonth(int $year, int $month): int
    {
        if ($month === 13) {
            return self::isEthiopianLeapYear($year) ? 6 : 5;
        }
        return 30;
    }

    /**
     * Returns the weekday (0=Sunday .. 6=Saturday) for a given Ethiopian date.
     *
     * @param array{year:int,month:int,day:int}|EthiopianDate $ethiopian
     */
    public static function getWeekday(array|EthiopianDate $ethiopian): int
    {
        $eth = $ethiopian instanceof EthiopianDate ? $ethiopian : EthiopianDate::fromArray($ethiopian);
        $g = Conversions::toGC($eth->year, $eth->month, $eth->day);

        // Zeller-independent: derive weekday from a known Julian Day Number anchor.
        $jdn = self::gregorianToJdn($g->year, $g->month, $g->day);
        // JDN 0 was a Monday; (jdn + 1) % 7 gives 0=Sunday .. 6=Saturday.
        return (int) self::mod($jdn + 1, 7);
    }

    /**
     * Converts a Gregorian date to a Julian Day Number (proleptic Gregorian calendar).
     */
    public static function gregorianToJdn(int $year, int $month, int $day): int
    {
        $a = intdiv(14 - $month, 12);
        $y = $year + 4800 - $a;
        $m = $month + 12 * $a - 3;

        return $day + intdiv(153 * $m + 2, 5) + 365 * $y + intdiv($y, 4) - intdiv($y, 100) + intdiv($y, 400) - 32045;
    }

    /**
     * Converts a Julian Day Number to a Gregorian date (proleptic Gregorian calendar).
     */
    public static function jdnToGregorian(int $jdn): GregorianDate
    {
        $a = $jdn + 32044;
        $b = intdiv(4 * $a + 3, 146097);
        $c = $a - intdiv(146097 * $b, 4);

        $d = intdiv(4 * $c + 3, 1461);
        $e = $c - intdiv(1461 * $d, 4);
        $m = intdiv(5 * $e + 2, 153);

        $day = $e - intdiv(153 * $m + 2, 5) + 1;
        $month = $m + 3 - 12 * intdiv($m, 10);
        $year = 100 * $b + $d - 4800 + intdiv($m, 10);

        return new GregorianDate($year, $month, $day);
    }

    /**
     * Checks if a given Ethiopian date is valid.
     */
    public static function isValidEthiopianDate(int $year, int $month, int $day): bool
    {
        if ($month < 1 || $month > 13) {
            return false;
        }
        return $day >= 1 && $day <= self::getEthiopianDaysInMonth($year, $month);
    }

    /**
     * Get the Gregorian date of the Ethiopian New Year for a given Gregorian year.
     *
     * @return array{gregorianYear: int, month: int, day: int}
     */
    public static function getEthiopianNewYearForGregorian(int $gYear): array
    {
        // Meskerem 1 always falls in September of Gregorian year G and begins
        // Ethiopian year G - 7.
        return self::getGregorianDateOfEthiopianNewYear($gYear - 7);
    }

    /**
     * Returns the Gregorian date of the Ethiopian New Year for the given Ethiopian year.
     *
     * Counts days from the known anchor Meskerem 1, 1964 = 1971-09-12 using the
     * Ethiopian leap rule (year % 4 === 3 has a 6-day Pagume), which has no
     * century exception. Deriving Sept 11/12 from the Gregorian leap rule only
     * holds for 1900-2099.
     *
     * @return array{gregorianYear: int, month: int, day: int}
     */
    public static function getGregorianDateOfEthiopianNewYear(int $ethiopianYear): array
    {
        $days = 365 * ($ethiopianYear - 1964) + self::floorDiv($ethiopianYear, 4) - self::floorDiv(1964, 4);
        $g = self::jdnToGregorian(self::gregorianToJdn(1971, 9, 12) + $days);
        return ['gregorianYear' => $g->year, 'month' => $g->month, 'day' => $g->day];
    }

    /**
     * A modulo that always returns a non-negative result for non-negative divisors,
     * matching JS's `((n % m) + m) % m` idiom used throughout the original source.
     */
    public static function mod(int $n, int $m): int
    {
        return (($n % $m) + $m) % $m;
    }

    /**
     * Floor division, matching JS's `Math.floor(a / b)`. PHP's intdiv() truncates
     * toward zero instead, which differs from floor() for negative numerators
     * (e.g. intdiv(-17, 1461) === -17, but Math.floor(-17.9) === -18).
     */
    public static function floorDiv(int $a, int $b): int
    {
        return (int) floor($a / $b);
    }
}
