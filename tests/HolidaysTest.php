<?php

declare(strict_types=1);

namespace Kenat\Tests;

use Kenat\BahireHasab;
use Kenat\Exceptions\InvalidInputTypeException;
use Kenat\Exceptions\UnknownHolidayException;
use Kenat\Holiday;
use Kenat\Holidays;
use PHPUnit\Framework\TestCase;

/**
 * Fixtures ported from kenat/tests/holidays.test.ts.
 */
final class HolidaysTest extends TestCase
{
    public function testGetHolidaysInMonthReturnsFixedAndMovable(): void
    {
        // Meskerem 2016 has Enkutatash (day 1) and Meskel (day 17).
        $holidays = Holidays::getHolidaysInMonth(2016, 1);
        $keys = array_map(static fn (Holiday $h) => $h->key, $holidays);

        $this->assertContains('enkutatash', $keys);
        $this->assertContains('meskel', $keys);
    }

    public function testGetHolidaysInMonthIncludesMovableChristianHolidays(): void
    {
        // In 2016 E.C., Fasika is on Miazia 27 and Siklet is on Miazia 25.
        $holidays = Holidays::getHolidaysInMonth(2016, 8);
        $byKey = [];
        foreach ($holidays as $h) {
            $byKey[$h->key] = $h;
        }

        $this->assertArrayHasKey('fasika', $byKey);
        $this->assertSame(27, $byKey['fasika']->ethiopian->day);
        $this->assertArrayHasKey('siklet', $byKey);
        $this->assertSame(25, $byKey['siklet']->ethiopian->day);
    }

    /**
     * @dataProvider fasikaSikletProvider
     */
    public function testGetMovableHolidayFasikaAndSiklet(int $year, array $fasika, array $siklet): void
    {
        $this->assertSame($fasika, BahireHasab::getMovableHoliday('TINSAYE', $year)->toArray());
        $this->assertSame($siklet, BahireHasab::getMovableHoliday('SIKLET', $year)->toArray());
    }

    public static function fasikaSikletProvider(): array
    {
        return [
            2012 => [2012, ['year' => 2012, 'month' => 8, 'day' => 11], ['year' => 2012, 'month' => 8, 'day' => 9]],
            2016 => [2016, ['year' => 2016, 'month' => 8, 'day' => 27], ['year' => 2016, 'month' => 8, 'day' => 25]],
        ];
    }

    public function testGetHolidaysInMonthThrowsForOutOfRangeMonth(): void
    {
        $this->expectException(InvalidInputTypeException::class);
        Holidays::getHolidaysInMonth(2016, 0);
    }

    public function testGetHolidaysInMonthThrowsForMonth14(): void
    {
        $this->expectException(InvalidInputTypeException::class);
        Holidays::getHolidaysInMonth(2016, 14);
    }

    public function testGetMovableHolidayThrowsForUnknownKey(): void
    {
        $this->expectException(UnknownHolidayException::class);
        BahireHasab::getMovableHoliday('UNKNOWN_HOLIDAY', 2016);
    }

    public function testGetHolidaysForYearFiltersByTag(): void
    {
        $publicHolidays = Holidays::getHolidaysForYear(2016, ['filter' => \Kenat\HolidayTags::PUBLIC]);
        $this->assertNotEmpty($publicHolidays);
        foreach ($publicHolidays as $h) {
            $this->assertContains(\Kenat\HolidayTags::PUBLIC, $h->tags);
        }
    }

    /**
     * Islamic holiday dates are approximate (tabular calendar, not ICU's
     * lunar-observation-informed one - see Conversions class docblock), so
     * we only assert that a plausible date within the Ethiopian year is found,
     * not an exact day.
     */
    public function testMoulidIsFoundWithinTheYear(): void
    {
        $holiday = Holidays::getHoliday('moulid', 2016);
        $this->assertNotNull($holiday);
        $this->assertSame(2016, $holiday->ethiopian->year);
        $this->assertTrue($holiday->movable);
    }

    public function testEidFitrIsFoundWithinTheYear(): void
    {
        $holiday = Holidays::getHoliday('eidFitr', 2016);
        $this->assertNotNull($holiday);
        $this->assertSame(2016, $holiday->ethiopian->year);
    }

    public function testUnknownHolidayKeyReturnsNull(): void
    {
        $this->assertNull(Holidays::getHoliday('not_a_real_holiday', 2016));
    }
}
