<?php

declare(strict_types=1);

namespace Kenat;

/**
 * addDays/addMonths/addYears and diff/diffBreakdown functions operating on
 * plain EthiopianDate values (not Kenat instances).
 *
 * Ported from kenat/src/dayArithmetic.ts.
 */
final class DayArithmetic
{
    private function __construct()
    {
    }

    /**
     * Adds a specified number of days to an Ethiopian date.
     */
    public static function addDays(EthiopianDate $ethiopian, int $days): EthiopianDate
    {
        $year = $ethiopian->year;
        $month = $ethiopian->month;
        $day = $ethiopian->day + $days;

        // Handle positive days (moving forward).
        while ($day > Utils::getEthiopianDaysInMonth($year, $month)) {
            $day -= Utils::getEthiopianDaysInMonth($year, $month);
            $month += 1;

            if ($month > 13) {
                $month = 1;
                $year += 1;
            }
        }

        // Handle negative days (moving backward).
        while ($day <= 0) {
            $month -= 1;
            if ($month < 1) {
                $month = 13;
                $year -= 1;
            }
            $day += Utils::getEthiopianDaysInMonth($year, $month);
        }

        return new EthiopianDate($year, $month, $day);
    }

    /**
     * Adds a specified number of months to an Ethiopian date.
     */
    public static function addMonths(EthiopianDate $ethiopian, int $months): EthiopianDate
    {
        $totalMonths = $ethiopian->month + $months;

        // PHP's intdiv() truncates toward zero, so floor() is used to match
        // JS's Math.floor((totalMonths - 1) / 13) for negative offsets too.
        $year = $ethiopian->year + (int) floor(($totalMonths - 1) / 13);
        $month = Utils::mod($totalMonths - 1, 13) + 1;

        $day = $ethiopian->day;
        $daysInTargetMonth = Utils::getEthiopianDaysInMonth($year, $month);
        if ($day > $daysInTargetMonth) {
            $day = $daysInTargetMonth;
        }

        return new EthiopianDate($year, $month, $day);
    }

    /**
     * Adds a specified number of years to an Ethiopian date.
     */
    public static function addYears(EthiopianDate $ethiopian, int $years): EthiopianDate
    {
        $year = $ethiopian->year + $years;
        $month = $ethiopian->month;
        $day = $ethiopian->day;

        if ($month === 13 && $day === 6 && !Utils::isEthiopianLeapYear($year)) {
            $day = 5;
        }

        return new EthiopianDate($year, $month, $day);
    }

    /**
     * Calculates the difference in days between two Ethiopian dates (a - b).
     */
    public static function diffInDays(EthiopianDate $a, EthiopianDate $b): int
    {
        $totalDays = static function (EthiopianDate $eth): int {
            $days = 0;
            for ($y = 1; $y < $eth->year; $y++) {
                $days += Utils::isEthiopianLeapYear($y) ? 366 : 365;
            }
            for ($m = 1; $m < $eth->month; $m++) {
                $days += Utils::getEthiopianDaysInMonth($eth->year, $m);
            }
            return $days + $eth->day;
        };

        return $totalDays($a) - $totalDays($b);
    }

    /**
     * Calculates the difference in months between two Ethiopian dates (a - b).
     */
    public static function diffInMonths(EthiopianDate $a, EthiopianDate $b): int
    {
        $totalMonthsA = $a->year * 13 + ($a->month - 1);
        $totalMonthsB = $b->year * 13 + ($b->month - 1);
        $diff = $totalMonthsA - $totalMonthsB;

        if ($a->day < $b->day) {
            $diff -= 1;
        }

        return $diff;
    }

    /**
     * Calculates the difference in years between two Ethiopian dates (a - b).
     */
    public static function diffInYears(EthiopianDate $a, EthiopianDate $b): int
    {
        $isAfter = ($a->year > $b->year)
            || ($a->year === $b->year && $a->month > $b->month)
            || ($a->year === $b->year && $a->month === $b->month && $a->day >= $b->day);

        [$later, $earlier] = $isAfter ? [$a, $b] : [$b, $a];
        $diff = $later->year - $earlier->year;

        if ($later->month < $earlier->month || ($later->month === $earlier->month && $later->day < $earlier->day)) {
            $diff--;
        }

        $finalDiff = $isAfter ? $diff : -$diff;

        return $finalDiff === 0 ? 0 : $finalDiff;
    }

    /**
     * Calculates a human-friendly breakdown between two Ethiopian dates.
     * Iteratively accumulates years, then months, then days to avoid off-by-one issues.
     *
     * @param string[] $units Units to include, in order. Defaults to ['years', 'months', 'days'].
     */
    public static function diffBreakdown(EthiopianDate $a, EthiopianDate $b, array $units = ['years', 'months', 'days']): DiffBreakdown
    {
        $totalDaysDiff = self::diffInDays($a, $b); // positive if a after b

        $sign = $totalDaysDiff === 0 ? 1 : ($totalDaysDiff > 0 ? 1 : -1);
        $later = $sign >= 0 ? $a : $b;
        $earlier = $sign >= 0 ? $b : $a;

        $cursor = $earlier;
        $years = null;
        $months = null;
        $days = null;

        if (in_array('years', $units, true)) {
            $years = 0;
            while (true) {
                $next = self::addYears($cursor, 1);
                if (self::diffInDays($later, $next) >= 0) {
                    $years += 1;
                    $cursor = $next;
                } else {
                    break;
                }
            }
        }

        if (in_array('months', $units, true)) {
            $months = 0;
            while (true) {
                $next = self::addMonths($cursor, 1);
                if (self::diffInDays($later, $next) >= 0) {
                    $months += 1;
                    $cursor = $next;
                } else {
                    break;
                }
            }
        }

        if (in_array('days', $units, true)) {
            $days = abs(self::diffInDays($later, $cursor));
        }

        return new DiffBreakdown($sign, abs($totalDaysDiff), $years, $months, $days);
    }
}
