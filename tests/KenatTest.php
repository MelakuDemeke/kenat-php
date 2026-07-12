<?php

declare(strict_types=1);

namespace Kenat\Tests;

use DateTimeImmutable;
use Kenat\EthiopianDate;
use Kenat\Exceptions\InvalidDateFormatException;
use Kenat\Exceptions\InvalidEthiopianDateException;
use Kenat\Kenat;
use PHPUnit\Framework\TestCase;

final class KenatTest extends TestCase
{
    public function testConstructsFromSlashString(): void
    {
        $k = new Kenat('2016/10/5');
        $this->assertSame(['year' => 2016, 'month' => 10, 'day' => 5], $k->getEthiopian()->toArray());
    }

    public function testConstructsFromDashString(): void
    {
        $k = new Kenat('2016-10-5');
        $this->assertSame(['year' => 2016, 'month' => 10, 'day' => 5], $k->getEthiopian()->toArray());
    }

    public function testConstructsFromArray(): void
    {
        $k = new Kenat(['year' => 2016, 'month' => 6, 'day' => 10]);
        $this->assertSame(['year' => 2016, 'month' => 6, 'day' => 10], $k->getEthiopian()->toArray());
    }

    public function testConstructsFromEthiopianDate(): void
    {
        $k = new Kenat(new EthiopianDate(2016, 6, 10));
        $this->assertSame(['year' => 2016, 'month' => 6, 'day' => 10], $k->getEthiopian()->toArray());
    }

    public function testConstructsFromGregorianDateTime(): void
    {
        $k = new Kenat(new DateTimeImmutable('2024-06-07'));
        $this->assertSame(['year' => 2016, 'month' => 9, 'day' => 30], $k->getEthiopian()->toArray());
    }

    public function testInvalidDateStringThrows(): void
    {
        $this->expectException(InvalidDateFormatException::class);
        new Kenat('not-a-date');
    }

    public function testInvalidEthiopianDateThrows(): void
    {
        $this->expectException(InvalidEthiopianDateException::class);
        new Kenat('2016/14/1');
    }

    public function testGetGregorian(): void
    {
        $k = new Kenat('2016/1/1');
        $this->assertSame(['year' => 2023, 'month' => 9, 'day' => 12], $k->getGregorian()->toArray());
    }

    public function testFormatStandardDefault(): void
    {
        $k = new Kenat('2016/1/1');
        $this->assertSame('መስከረም 1 2016', $k->format());
    }

    public function testFormatInGeezAmharic(): void
    {
        $k = new Kenat('2016/10/5');
        $this->assertSame('ሰኔ ፭ ፳፻፲፮', $k->formatInGeezAmharic());
    }

    public function testFormatShort(): void
    {
        $k = new Kenat('2016/1/5');
        $this->assertSame('2016/01/05', $k->formatShort());
    }

    public function testAddDaysChaining(): void
    {
        $k = new Kenat('2016/1/10');
        $result = $k->addDays(5);
        $this->assertSame(['year' => 2016, 'month' => 1, 'day' => 15], $result->getEthiopian()->toArray());
    }

    public function testAddMonthsThenAddYearsChaining(): void
    {
        $k = new Kenat('2015/6/10');
        $result = $k->addDays(5)->addMonths(-1)->addYears(2);
        $this->assertSame(['year' => 2017, 'month' => 5, 'day' => 15], $result->getEthiopian()->toArray());
    }

    public function testOriginalInstanceIsImmutable(): void
    {
        $k = new Kenat('2016/1/10');
        $k->addDays(5);
        $this->assertSame(['year' => 2016, 'month' => 1, 'day' => 10], $k->getEthiopian()->toArray());
    }

    public function testDiffInDays(): void
    {
        $a = new Kenat('2016/06/10');
        $b = new Kenat('2016/06/05');
        $this->assertSame(5, $a->diffInDays($b));
    }

    public function testIsBeforeIsAfterIsSameDay(): void
    {
        $a = new Kenat('2016/06/10');
        $b = new Kenat('2016/06/05');
        $this->assertTrue($b->isBefore($a));
        $this->assertTrue($a->isAfter($b));
        $this->assertTrue($a->isSameDay(new Kenat('2016/06/10')));
    }

    public function testIsLeapYear(): void
    {
        $this->assertTrue((new Kenat('2011/1/1'))->isLeapYear());
        $this->assertFalse((new Kenat('2012/1/1'))->isLeapYear());
    }

    public function testStartOfMonthAndEndOfMonth(): void
    {
        $k = new Kenat('2016/6/15');
        $this->assertSame(['year' => 2016, 'month' => 6, 'day' => 1], $k->startOfMonth()->getEthiopian()->toArray());
        $this->assertSame(['year' => 2016, 'month' => 6, 'day' => 30], $k->endOfMonth()->getEthiopian()->toArray());
    }

    public function testEndOfYearHandlesPagume(): void
    {
        // 2011 is a leap year -> Pagume has 6 days.
        $k = new Kenat('2011/1/1');
        $this->assertSame(['year' => 2011, 'month' => 13, 'day' => 6], $k->endOf('year')->getEthiopian()->toArray());
    }

    public function testGenerateDateRange(): void
    {
        $start = new Kenat('2016/1/1');
        $end = new Kenat('2016/1/5');
        $range = Kenat::generateDateRange($start, $end);
        $this->assertCount(5, $range);
        $this->assertSame(1, $range[0]->getEthiopian()->day);
        $this->assertSame(5, $range[4]->getEthiopian()->day);
    }

    public function testToArrayShape(): void
    {
        $k = new Kenat('2016/1/1');
        $arr = $k->toArray();
        $this->assertSame(['year' => 2016, 'month' => 1, 'day' => 1], $arr['ethiopian']);
        $this->assertArrayHasKey('gregorian', $arr);
        $this->assertArrayHasKey('time', $arr);
    }

    public function testIsHolidayOnEnkutatash(): void
    {
        $k = new Kenat('2016/1/1');
        $holidays = $k->isHoliday();
        $keys = array_map(static fn ($h) => $h->key, $holidays);
        $this->assertContains('enkutatash', $keys);
    }

    public function testGetBahireHasab(): void
    {
        $k = new Kenat('2016/1/1');
        $bh = $k->getBahireHasab();
        $this->assertSame(7516, $bh['ameteAlem']);
    }
}
