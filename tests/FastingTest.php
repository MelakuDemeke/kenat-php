<?php

declare(strict_types=1);

namespace Kenat\Tests;

use Kenat\Fasting;
use Kenat\FastingKeys;
use PHPUnit\Framework\TestCase;

final class FastingTest extends TestCase
{
    public function testAbiyTsomePeriodFor2016(): void
    {
        // Abiy Tsome starts and Siklet (Good Friday) ends the period.
        $period = Fasting::getFastingPeriod(FastingKeys::ABIY_TSOME, 2016);
        $this->assertNotNull($period);
        $this->assertSame(['year' => 2016, 'month' => 7, 'day' => 2], $period->start->toArray());
        $this->assertSame(['year' => 2016, 'month' => 8, 'day' => 25], $period->end->toArray());
    }

    public function testNinevehPeriodIsThreeDaysInclusive(): void
    {
        $period = Fasting::getFastingPeriod(FastingKeys::NINEVEH, 2016);
        $this->assertNotNull($period);
        $this->assertSame(['year' => 2016, 'month' => 6, 'day' => 18], $period->start->toArray());
        $this->assertSame(['year' => 2016, 'month' => 6, 'day' => 20], $period->end->toArray());
    }

    public function testFilsetaIsFixed(): void
    {
        $period = Fasting::getFastingPeriod(FastingKeys::FILSETA, 2016);
        $this->assertSame(['year' => 2016, 'month' => 12, 'day' => 1], $period->start->toArray());
        $this->assertSame(['year' => 2016, 'month' => 12, 'day' => 14], $period->end->toArray());
    }

    public function testGetFastingInfoReturnsNameAndDescription(): void
    {
        $info = Fasting::getFastingInfo(FastingKeys::NINEVEH, 2016, ['lang' => 'english']);
        $this->assertNotNull($info);
        $this->assertSame('Fast of Nineveh', $info['name']);
        $this->assertNotNull($info['period']);
    }

    public function testTsomeDihenetHasNoContiguousPeriod(): void
    {
        $info = Fasting::getFastingInfo(FastingKeys::TSOME_DIHENET, 2016, ['lang' => 'english']);
        $this->assertNotNull($info);
        $this->assertNull($info['period']);
    }

    public function testGetFastingDaysForFilsetaWithinNehase(): void
    {
        // Nehase is Ethiopian month 12; Filseta runs day 1-14.
        $days = Fasting::getFastingDays(FastingKeys::FILSETA, 2016, 12);
        $this->assertSame(range(1, 14), $days);
    }

    public function testGetFastingDaysOutsideRangeIsEmpty(): void
    {
        $days = Fasting::getFastingDays(FastingKeys::FILSETA, 2016, 1);
        $this->assertSame([], $days);
    }

    public function testUnknownFastKeyReturnsNull(): void
    {
        $this->assertNull(Fasting::getFastingPeriod('NOT_A_FAST', 2016));
    }
}
