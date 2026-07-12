<?php

declare(strict_types=1);

namespace Kenat\Tests;

use Kenat\DayArithmetic;
use Kenat\EthiopianDate;
use PHPUnit\Framework\TestCase;

/**
 * Fixtures ported from kenat/tests/dayArtimetic.test.ts (exercised through
 * DayArithmetic directly instead of via the Kenat class).
 */
final class DayArithmeticTest extends TestCase
{
    public function testAddDaysWithinSameMonth(): void
    {
        $result = DayArithmetic::addDays(new EthiopianDate(2016, 1, 10), 5);
        $this->assertSame(['year' => 2016, 'month' => 1, 'day' => 15], $result->toArray());
    }

    public function testAddDaysCrossingMonthBoundary(): void
    {
        $result = DayArithmetic::addDays(new EthiopianDate(2016, 1, 28), 5);
        $this->assertSame(['year' => 2016, 'month' => 2, 'day' => 3], $result->toArray());
    }

    public function testAddDaysCrossingYearBoundary(): void
    {
        $result = DayArithmetic::addDays(new EthiopianDate(2016, 13, 4), 3);
        $this->assertSame(['year' => 2017, 'month' => 1, 'day' => 2], $result->toArray());
    }

    public function testAddZeroDays(): void
    {
        $result = DayArithmetic::addDays(new EthiopianDate(2016, 5, 15), 0);
        $this->assertSame(['year' => 2016, 'month' => 5, 'day' => 15], $result->toArray());
    }

    public function testAddMonthsWithinSameYear(): void
    {
        $result = DayArithmetic::addMonths(new EthiopianDate(2016, 3, 10), 5);
        $this->assertSame(['year' => 2016, 'month' => 8, 'day' => 10], $result->toArray());
    }

    public function testAddMonthsWithYearRollover(): void
    {
        $result = DayArithmetic::addMonths(new EthiopianDate(2016, 11, 10), 3);
        $this->assertSame(['year' => 2017, 'month' => 1, 'day' => 10], $result->toArray());
    }

    public function testAddMonthsResultingInPagumeWithDayClamp(): void
    {
        // 2015 is not a leap year (Pagume = 5 days).
        $result = DayArithmetic::addMonths(new EthiopianDate(2015, 12, 30), 1);
        $this->assertSame(['year' => 2015, 'month' => 13, 'day' => 6], $result->toArray());
    }

    public function testAddMonthsResultingInPagumeInLeapYear(): void
    {
        // 2011 is a leap year (Pagume = 6 days).
        $result = DayArithmetic::addMonths(new EthiopianDate(2011, 12, 30), 1);
        $this->assertSame(['year' => 2011, 'month' => 13, 'day' => 6], $result->toArray());
    }

    public function testAddNegativeMonthsAcrossYearBoundary(): void
    {
        $result = DayArithmetic::addMonths(new EthiopianDate(2016, 3, 10), -4);
        $this->assertSame(['year' => 2015, 'month' => 12, 'day' => 10], $result->toArray());
    }

    public function testSubtractIntoPagumeWithClamping(): void
    {
        $result = DayArithmetic::addMonths(new EthiopianDate(2016, 1, 6), -1);
        $this->assertSame(['year' => 2015, 'month' => 13, 'day' => 6], $result->toArray());
    }

    public function testAddYearsFromLeapPagume6ToNonLeapYear(): void
    {
        $result = DayArithmetic::addYears(new EthiopianDate(2011, 13, 6), 1);
        $this->assertSame(['year' => 2012, 'month' => 13, 'day' => 5], $result->toArray());
    }

    public function testAddYearsToAnotherLeapYearKeepingPagume6(): void
    {
        $result = DayArithmetic::addYears(new EthiopianDate(2011, 13, 6), 4);
        $this->assertSame(['year' => 2015, 'month' => 13, 'day' => 6], $result->toArray());
    }

    public function testDiffInDaysSameDate(): void
    {
        $this->assertSame(0, DayArithmetic::diffInDays(new EthiopianDate(2016, 5, 15), new EthiopianDate(2016, 5, 15)));
    }

    public function testDiffInDaysLaterMinusEarlier(): void
    {
        $this->assertSame(5, DayArithmetic::diffInDays(new EthiopianDate(2016, 6, 10), new EthiopianDate(2016, 6, 5)));
    }

    public function testDiffInDaysCrossingMultipleYears(): void
    {
        $this->assertSame(730, DayArithmetic::diffInDays(new EthiopianDate(2018, 1, 1), new EthiopianDate(2016, 1, 1)));
    }

    public function testDiffInMonthsWithDayAdjustment(): void
    {
        // totalMonths difference is -1, but day 1 < 6 subtracts 1 more month = -2.
        $this->assertSame(-2, DayArithmetic::diffInMonths(new EthiopianDate(2016, 5, 1), new EthiopianDate(2016, 6, 6)));
    }

    public function testDiffInMonthsCrossingMultipleYears(): void
    {
        $this->assertSame(26, DayArithmetic::diffInMonths(new EthiopianDate(2018, 1, 1), new EthiopianDate(2016, 1, 1)));
    }

    public function testDiffInYearsCrossingMultipleYears(): void
    {
        $this->assertSame(2, DayArithmetic::diffInYears(new EthiopianDate(2018, 1, 1), new EthiopianDate(2016, 1, 1)));
    }

    public function testDiffInYearsNegative(): void
    {
        $this->assertSame(-2, DayArithmetic::diffInYears(new EthiopianDate(2016, 1, 1), new EthiopianDate(2018, 1, 1)));
    }

    public function testDiffBreakdown(): void
    {
        $breakdown = DayArithmetic::diffBreakdown(new EthiopianDate(2018, 3, 15), new EthiopianDate(2016, 1, 1));
        $this->assertSame(1, $breakdown->sign);
        $this->assertSame(2, $breakdown->years);
        $this->assertSame(2, $breakdown->months);
        $this->assertSame(14, $breakdown->days);
    }
}
