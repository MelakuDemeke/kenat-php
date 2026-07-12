<?php

declare(strict_types=1);

namespace Kenat;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Kenat\Exceptions\InvalidEthiopianDateException;
use Kenat\Exceptions\InvalidGregorianDateException;

/**
 * The Ethiopian<->Gregorian conversion core, plus Hijri/Islamic-calendar
 * conversion for the Muslim holidays (Eid al-Fitr, Eid al-Adha, Moulid, Ramadan).
 *
 * Ported from kenat/src/conversions.ts. The TS original derives Hijri dates from
 * the JS runtime's ICU islamic calendar (`Intl.DateTimeFormat('en-TN-u-ca-islamic')`).
 * PHP has no equivalent built-in binding without requiring ext-intl, so this port
 * uses a self-contained tabular Islamic calendar (Julian-Day-Number based) instead,
 * the same approach already used by the kenat_py port. This can disagree with ICU's
 * lunar-observation-informed calendar by a day at some month boundaries (e.g. Ramadan
 * start/end) - a known, accepted limitation, not a bug to chase down here.
 */
final class Conversions
{
    /** @var array<string, ?DateTimeImmutable> */
    private static array $hijriToGregorianCache = [];

    private function __construct()
    {
    }

    /**
     * Converts an Ethiopian date to its corresponding Gregorian date.
     *
     * @throws InvalidEthiopianDateException If the provided Ethiopian date is invalid.
     */
    public static function toGC(int $ethYear, int $ethMonth, int $ethDay): GregorianDate
    {
        if ($ethMonth < 1 || $ethMonth > 13) {
            throw new InvalidEthiopianDateException($ethYear, $ethMonth, $ethDay);
        }
        $maxDay = $ethMonth === 13 ? (Utils::isEthiopianLeapYear($ethYear) ? 6 : 5) : 30;
        if ($ethDay < 1 || $ethDay > $maxDay) {
            throw new InvalidEthiopianDateException($ethYear, $ethMonth, $ethDay);
        }

        $newYear = Utils::getGregorianDateOfEthiopianNewYear($ethYear);
        $daysSinceNewYear = ($ethMonth - 1) * 30 + $ethDay - 1;
        $newYearDOY = Utils::dayOfYear($newYear['gregorianYear'], $newYear['month'], $newYear['day']);
        $gregorianDOY = $newYearDOY + $daysSinceNewYear;
        $gregorianYear = $newYear['gregorianYear'];
        $yearLength = Utils::isGregorianLeapYear($gregorianYear) ? 366 : 365;

        if ($gregorianDOY > $yearLength) {
            $gregorianDOY -= $yearLength;
            $gregorianYear += 1;
        }

        $monthDay = Utils::monthDayFromDayOfYear($gregorianYear, $gregorianDOY);

        return new GregorianDate($gregorianYear, $monthDay['month'], $monthDay['day']);
    }

    /**
     * Converts a Gregorian date to the Ethiopian calendar (EC) date.
     *
     * @throws InvalidGregorianDateException If the input date is invalid or out of the 1900-2100 range.
     */
    public static function toEC(int $gYear, int $gMonth, int $gDay): EthiopianDate
    {
        if (!checkdate($gMonth, $gDay, $gYear) || $gYear < 1900 || $gYear > 2100) {
            throw new InvalidGregorianDateException($gYear, $gMonth, $gDay);
        }

        $inputJdn = Utils::gregorianToJdn($gYear, $gMonth, $gDay);
        $baseJdn = Utils::gregorianToJdn(1971, 9, 12);

        $diffDays = $inputJdn - $baseJdn;
        // Dates before the 1971-09-12 base (e.g. the 1900-1971 tail of the
        // supported range) make $diffDays negative, so this must floor toward
        // negative infinity like JS's Math.floor(), not truncate toward zero.
        $fourYearCycles = Utils::floorDiv($diffDays, 1461);
        $remainingDays = $diffDays - $fourYearCycles * 1461;
        $remainingYears = intdiv($remainingDays, 365);

        if ($remainingYears === 4) {
            $remainingYears = 3;
        }

        $remainingMonths = intdiv($remainingDays - $remainingYears * 365, 30);
        $remainingDayOfMonth = $remainingDays - $remainingYears * 365 - $remainingMonths * 30;

        $ethYear = 1964 + $fourYearCycles * 4 + $remainingYears;
        $month = $remainingMonths + 1;
        $day = $remainingDayOfMonth + 1;

        return new EthiopianDate($ethYear, $month, $day);
    }

    /**
     * Converts an Ethiopian date to a Gregorian calendar DateTimeImmutable (UTC midnight).
     */
    public static function toGCDate(int $ethYear, int $ethMonth, int $ethDay): DateTimeImmutable
    {
        $g = self::toGC($ethYear, $ethMonth, $ethDay);
        return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $g->year, $g->month, $g->day), new DateTimeZone('UTC'));
    }

    /**
     * Converts a Gregorian DateTimeInterface to the Ethiopian Calendar (EC) date.
     */
    public static function fromDateToEC(DateTimeInterface $date): EthiopianDate
    {
        return self::toEC((int) $date->format('Y'), (int) $date->format('n'), (int) $date->format('j'));
    }

    // --- Hijri (Islamic calendar) conversions ---

    /**
     * Gets the Hijri year for a Gregorian date, using the tabular Islamic calendar.
     */
    public static function getHijriYear(DateTimeInterface $date): int
    {
        $jdn = Utils::gregorianToJdn((int) $date->format('Y'), (int) $date->format('n'), (int) $date->format('j'));
        return self::jdnToHijri($jdn)['year'];
    }

    /**
     * Converts a Hijri date to the corresponding Gregorian date within a given
     * Gregorian year, by scanning day-by-day (mirrors the brute-force search
     * methodology of the original JS implementation).
     */
    public static function hijriToGregorian(int $hYear, int $hMonth, int $hDay, int $gregorianYear): ?DateTimeImmutable
    {
        $cacheKey = "{$hYear}-{$hMonth}-{$hDay}-{$gregorianYear}";
        if (array_key_exists($cacheKey, self::$hijriToGregorianCache)) {
            return self::$hijriToGregorianCache[$cacheKey];
        }

        $baseJdn = Utils::gregorianToJdn($gregorianYear - 1, 1, 1);

        for ($offset = 0; $offset <= 730; $offset++) {
            $jdn = $baseJdn + $offset;
            $hijri = self::jdnToHijri($jdn);

            if ($hijri['year'] === $hYear && $hijri['month'] === $hMonth && $hijri['day'] === $hDay) {
                $gregorian = self::jdnToGregorian($jdn);
                if ($gregorian->year === $gregorianYear) {
                    $result = new DateTimeImmutable(
                        sprintf('%04d-%02d-%02d', $gregorian->year, $gregorian->month, $gregorian->day),
                        new DateTimeZone('UTC')
                    );
                    self::$hijriToGregorianCache[$cacheKey] = $result;
                    return $result;
                }
            }
        }

        self::$hijriToGregorianCache[$cacheKey] = null;
        return null;
    }

    /**
     * Converts a Julian Day Number to a Gregorian date (proleptic Gregorian calendar).
     */
    private static function jdnToGregorian(int $jdn): GregorianDate
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
     * Converts a Julian Day Number to a Hijri (tabular Islamic calendar) date.
     *
     * Same epoch/formula as kenat_py's `_jd_to_hijri` (a standard tabular Islamic
     * calendar algorithm), adapted to integer JDN input instead of a
     * fractional-day Julian Date.
     *
     * @return array{year: int, month: int, day: int}
     */
    private static function jdnToHijri(int $jdn): array
    {
        // kenat_py takes `floor(jd) + 0.5 - 1948439.5`; for an integer JDN input
        // that reduces to a plain integer offset.
        $jd = $jdn - 1948439;

        $year = (int) floor((30 * $jd + 10646) / 10631);
        $startOfYear = 354 * ($year - 1) + (int) floor((3 + 11 * $year) / 30);
        $dayOfYear = $jd - $startOfYear;
        $month = (int) floor($dayOfYear / 29.5) + 1;
        if ($month > 12) {
            $month = 12;
        }
        $startOfMonth = 29.5 * ($month - 1);
        $day = (int) floor($jd - ($startOfYear + $startOfMonth)) + 1;

        return ['year' => $year, 'month' => $month, 'day' => $day];
    }
}
