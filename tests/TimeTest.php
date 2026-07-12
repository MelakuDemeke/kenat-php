<?php

declare(strict_types=1);

namespace Kenat\Tests;

use Kenat\Exceptions\InvalidInputTypeException;
use Kenat\Exceptions\InvalidTimeException;
use Kenat\Time;
use PHPUnit\Framework\TestCase;

/**
 * Fixtures ported from kenat/tests/Time.test.ts.
 */
final class TimeTest extends TestCase
{
    public function testConstructorValidTime(): void
    {
        $time = new Time(3, 30, 'day');
        $this->assertSame(3, $time->hour);
        $this->assertSame(30, $time->minute);
        $this->assertSame('day', $time->period);
    }

    public function testDefaultsMinuteAndPeriod(): void
    {
        $time = new Time(5);
        $this->assertSame(0, $time->minute);
        $this->assertSame('day', $time->period);
    }

    public function testThrowsForHourZero(): void
    {
        $this->expectException(InvalidTimeException::class);
        new Time(0, 0, 'day');
    }

    public function testThrowsForHour13(): void
    {
        $this->expectException(InvalidTimeException::class);
        new Time(13, 0, 'day');
    }

    public function testThrowsForInvalidPeriod(): void
    {
        $this->expectException(InvalidTimeException::class);
        new Time(5, 0, 'morning');
    }

    public function testFromStringWithArabicNumerals(): void
    {
        $t1 = Time::fromString('10:30 day');
        $this->assertSame(10, $t1->hour);
        $this->assertSame(30, $t1->minute);
        $this->assertSame('day', $t1->period);

        $t2 = Time::fromString('5:00 night');
        $this->assertSame(5, $t2->hour);
        $this->assertSame('night', $t2->period);
    }

    public function testFromStringWithGeezNumerals(): void
    {
        $t = Time::fromString('፫:፲፭ ማታ');
        $this->assertSame(3, $t->hour);
        $this->assertSame(15, $t->minute);
        $this->assertSame('night', $t->period);
    }

    public function testFromStringDefaultsToDayPeriod(): void
    {
        $t = Time::fromString('11:45');
        $this->assertSame('day', $t->period);
    }

    public function testFromStringHandlesInconsistentSpacing(): void
    {
        $t = Time::fromString(' 4 : 20  night ');
        $this->assertSame(4, $t->hour);
        $this->assertSame(20, $t->minute);
        $this->assertSame('night', $t->period);
    }

    public function testFromStringThrowsWithoutColon(): void
    {
        $this->expectException(InvalidTimeException::class);
        Time::fromString('10 30 day');
    }

    public function testFromStringThrowsForEmptyString(): void
    {
        $this->expectException(InvalidTimeException::class);
        Time::fromString('');
    }

    /**
     * @dataProvider fromGregorianProvider
     */
    public function testFromGregorian(int $gHour, int $gMinute, int $expectedHour, string $expectedPeriod): void
    {
        $t = Time::fromGregorian($gHour, $gMinute);
        $this->assertSame($expectedHour, $t->hour);
        $this->assertSame($expectedPeriod, $t->period);
    }

    public static function fromGregorianProvider(): array
    {
        return [
            [7, 30, 1, 'day'],
            [18, 0, 12, 'night'],
            [0, 0, 6, 'night'],
            [6, 0, 12, 'day'],
        ];
    }

    public function testFromGregorianThrowsForInvalidHour(): void
    {
        $this->expectException(InvalidTimeException::class);
        Time::fromGregorian(24, 0);
    }

    /**
     * @dataProvider toGregorianProvider
     */
    public function testToGregorian(int $hour, int $minute, string $period, array $expected): void
    {
        $t = new Time($hour, $minute, $period);
        $this->assertSame($expected, $t->toGregorian());
    }

    public static function toGregorianProvider(): array
    {
        return [
            [1, 30, 'day', ['hour' => 7, 'minute' => 30]],
            [12, 0, 'night', ['hour' => 18, 'minute' => 0]],
            [6, 0, 'night', ['hour' => 0, 'minute' => 0]],
            [12, 0, 'day', ['hour' => 6, 'minute' => 0]],
        ];
    }

    public function testAddWithinSamePeriod(): void
    {
        $startTime = new Time(3, 15, 'day'); // 9:15 AM
        $newTime = $startTime->add(['hours' => 2, 'minutes' => 10]);
        $this->assertSame(5, $newTime->hour);
        $this->assertSame(25, $newTime->minute);
        $this->assertSame('day', $newTime->period);
    }

    public function testAddRollsOverToNextPeriod(): void
    {
        $startTime = new Time(3, 15, 'day'); // 9:15 AM + 9 hours = 6:15 PM
        $newTime = $startTime->add(['hours' => 9]);
        $this->assertSame(12, $newTime->hour);
        $this->assertSame(15, $newTime->minute);
        $this->assertSame('night', $newTime->period);
    }

    public function testSubtract(): void
    {
        $startTime = new Time(3, 15, 'day');
        $newTime = $startTime->subtract(['hours' => 1, 'minutes' => 15]);
        $this->assertSame(2, $newTime->hour);
        $this->assertSame(0, $newTime->minute);
        $this->assertSame('day', $newTime->period);
    }

    public function testSubtractRollsBackToPreviousPeriod(): void
    {
        $newTime = (new Time(1, 0, 'day'))->subtract(['hours' => 2]); // 7:00 AM - 2h = 5:00 AM
        $this->assertSame(11, $newTime->hour);
        $this->assertSame('night', $newTime->period);
    }

    public function testDiff(): void
    {
        $startTime = new Time(3, 15, 'day');
        $endTime = new Time(5, 45, 'day');
        $this->assertSame(['hours' => 2, 'minutes' => 30], $startTime->diff($endTime));
    }

    public function testDiffAcross24hWrap(): void
    {
        $t1 = new Time(2, 0, 'night'); // 8 PM
        $t2 = new Time(10, 0, 'night'); // 4 AM
        $this->assertSame(['hours' => 8, 'minutes' => 0], $t1->diff($t2));
    }

    public function testFormatDefaultGeez(): void
    {
        $time = new Time(5, 30, 'day');
        $this->assertSame('፭:፴ ጠዋት', $time->format());
    }

    public function testFormatArabicNumerals(): void
    {
        $time = new Time(5, 30, 'day');
        $this->assertSame('05:30 day', $time->format(['useGeez' => false]));
    }

    public function testFormatWithoutPeriodLabel(): void
    {
        $time = new Time(8, 15, 'night');
        $this->assertSame('08:15', $time->format(['useGeez' => false, 'showPeriodLabel' => false]));
    }

    public function testFormatZeroMinutesAsDash(): void
    {
        $time = new Time(12, 0, 'day');
        $this->assertSame('12:_ day', $time->format(['useGeez' => false, 'zeroAsDash' => true]));
    }
}
