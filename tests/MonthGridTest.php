<?php

declare(strict_types=1);

namespace Kenat\Tests;

use Kenat\Exceptions\InvalidGridConfigException;
use Kenat\MonthGrid;
use PHPUnit\Framework\TestCase;

final class MonthGridTest extends TestCase
{
    public function testGeneratesHeadersAndMonthName(): void
    {
        $grid = MonthGrid::create(['year' => 2016, 'month' => 1, 'weekStart' => 1]);
        $this->assertSame('መስከረም', $grid['monthName']);
        $this->assertSame(2016, $grid['year']);
        $this->assertSame(1, $grid['month']);
        $this->assertSame(['ሰኞ', 'ማክሰኞ', 'ረቡዕ', 'ሐሙስ', 'ዓርብ', 'ቅዳሜ', 'እሑድ'], $grid['headers']);
    }

    public function testPublicModeIncludesFixedHolidays(): void
    {
        $grid = MonthGrid::create(['year' => 2016, 'month' => 1, 'mode' => 'public']);

        $holidaysByDay = [];
        foreach ($grid['days'] as $day) {
            if ($day === null) {
                continue;
            }
            foreach ($day['holidays'] as $h) {
                $holidaysByDay[(int) $day['ethiopian']['day']][] = $h['key'] ?? null;
            }
        }

        $this->assertArrayHasKey(1, $holidaysByDay);
        $this->assertContains('enkutatash', $holidaysByDay[1]);
        $this->assertArrayHasKey(17, $holidaysByDay);
        $this->assertContains('meskel', $holidaysByDay[17]);
    }

    public function testChristianModeExcludesNonReligiousFixedHolidays(): void
    {
        // Enkutatash is tagged public/cultural (not christian/religious), so it
        // should be filtered out in 'christian' mode - only Meskel remains.
        $grid = MonthGrid::create(['year' => 2016, 'month' => 1, 'mode' => 'christian']);

        $day1 = null;
        foreach ($grid['days'] as $day) {
            if ($day !== null && (int) $day['ethiopian']['day'] === 1) {
                $day1 = $day;
                break;
            }
        }

        $this->assertNotNull($day1);
        $keys = array_map(static fn ($h) => $h['key'] ?? null, $day1['holidays']);
        $this->assertNotContains('enkutatash', $keys);
    }

    public function testChristianModeIncludesSaintsDays(): void
    {
        $grid = MonthGrid::create(['year' => 2016, 'month' => 1, 'mode' => 'christian']);

        $hasSaintEntry = false;
        foreach ($grid['days'] as $day) {
            if ($day === null) {
                continue;
            }
            foreach ($day['holidays'] as $h) {
                if (($h['isNigs'] ?? null) !== null) {
                    $hasSaintEntry = true;
                    break 2;
                }
            }
        }

        $this->assertTrue($hasSaintEntry);
    }

    public function testUseGeezRendersGeezNumerals(): void
    {
        $grid = MonthGrid::create(['year' => 2016, 'month' => 1, 'useGeez' => true]);
        $this->assertSame('፳፻፲፮', $grid['year']);
    }

    public function testUpNavigatesToNextMonth(): void
    {
        $grid = MonthGrid::create(['year' => 2016, 'month' => 13]);
        $next = ($grid['up'])();
        $this->assertSame(1, $next['month']);
        $this->assertSame(2017, $next['year']);
    }

    public function testDownNavigatesToPreviousMonth(): void
    {
        $grid = MonthGrid::create(['year' => 2016, 'month' => 1]);
        $prev = ($grid['down'])();
        $this->assertSame(13, $prev['month']);
        $this->assertSame(2015, $prev['year']);
    }

    public function testInvalidWeekStartThrows(): void
    {
        $this->expectException(InvalidGridConfigException::class);
        new MonthGrid(['weekStart' => 7]);
    }

    public function testYearWithoutMonthThrows(): void
    {
        $this->expectException(InvalidGridConfigException::class);
        new MonthGrid(['year' => 2016]);
    }
}
