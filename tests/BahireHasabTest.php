<?php

declare(strict_types=1);

namespace Kenat\Tests;

use Kenat\BahireHasab;
use Kenat\Exceptions\UnknownHolidayException;
use PHPUnit\Framework\TestCase;

/**
 * Fixtures ported from kenat/tests/bahireHasab.test.ts.
 */
final class BahireHasabTest extends TestCase
{
    public function testAmeteAlemAndMeteneRabietFor2016(): void
    {
        $bh = BahireHasab::calculate(2016);
        $this->assertSame(7516, $bh['ameteAlem']);
        $this->assertSame(1879, $bh['meteneRabiet']);
    }

    public function testEvangelistFor2016(): void
    {
        $bh = BahireHasab::calculate(2016);
        $this->assertSame('ዮሐንስ', $bh['evangelist']['name']);
        $this->assertSame(0, $bh['evangelist']['remainder']);
    }

    public function testNewYearDayFor2016(): void
    {
        $bh = BahireHasab::calculate(2016);
        $this->assertSame('ማክሰኞ', $bh['newYear']['dayName']);
    }

    public function testMedebWenberAbektieMetqiFor2016(): void
    {
        $bh = BahireHasab::calculate(2016);
        $this->assertSame(11, $bh['medeb']);
        $this->assertSame(10, $bh['wenber']);
        $this->assertSame(20, $bh['abektie']);
        $this->assertSame(10, $bh['metqi']);
    }

    public function testNinevehDateFor2016(): void
    {
        $bh = BahireHasab::calculate(2016);
        $this->assertSame(['year' => 2016, 'month' => 6, 'day' => 18], $bh['nineveh']->toArray());
    }

    public function testMetqi30EdgeCaseFor2006DoesNotThrow(): void
    {
        $bh = BahireHasab::calculate(2006);
        $this->assertSame(30, $bh['metqi']);
        $this->assertSame(['year' => 2006, 'month' => 1, 'day' => 30], $bh['bealeMetqi']['date']->toArray());
        $this->assertSame(3, $bh['mebajaHamer']);
        $this->assertSame(['year' => 2006, 'month' => 6, 'day' => 3], $bh['nineveh']->toArray());
    }

    public function testFasikaFor2006(): void
    {
        $bh = BahireHasab::calculate(2006);
        $fasika = $bh['movableFeasts']['fasika'];
        $this->assertSame(['year' => 2006, 'month' => 8, 'day' => 12], $fasika->ethiopian->toArray());
        $this->assertSame(['year' => 2014, 'month' => 4, 'day' => 20], $fasika->gregorian->toArray());
    }

    public function testEnglishNames(): void
    {
        $bh = BahireHasab::calculate(2016, ['lang' => 'english']);
        $this->assertSame('John', $bh['evangelist']['name']);
        $this->assertSame('Tuesday', $bh['newYear']['dayName']);
    }

    public function testMovableFeastsShapeAndFasika(): void
    {
        $bh = BahireHasab::calculate(2016, ['lang' => 'english']);
        $movableFeasts = $bh['movableFeasts'];

        $this->assertGreaterThan(5, count($movableFeasts));

        $fasika = $movableFeasts['fasika'];
        $this->assertSame(['year' => 2016, 'month' => 8, 'day' => 27], $fasika->ethiopian->toArray());
        $this->assertSame('Ethiopian Easter', $fasika->name);
        $this->assertContains('public', $fasika->tags);
    }

    public function testAbiyTsomeFor2016(): void
    {
        $bh = BahireHasab::calculate(2016, ['lang' => 'english']);
        $abiyTsome = $bh['movableFeasts']['abiyTsome'];
        $this->assertSame(['year' => 2016, 'month' => 7, 'day' => 2], $abiyTsome->ethiopian->toArray());
        $this->assertSame('Great Lent', $abiyTsome->name);
    }

    /**
     * @dataProvider fasikaSikletProvider
     */
    public function testFasikaAndSikletDates(int $year, array $fasika, array $siklet): void
    {
        $this->assertSame($fasika, BahireHasab::getMovableHoliday('TINSAYE', $year)->toArray());
        $this->assertSame($siklet, BahireHasab::getMovableHoliday('SIKLET', $year)->toArray());
    }

    public static function fasikaSikletProvider(): array
    {
        return [
            2012 => [2012, ['year' => 2012, 'month' => 8, 'day' => 11], ['year' => 2012, 'month' => 8, 'day' => 9]],
            2013 => [2013, ['year' => 2013, 'month' => 8, 'day' => 24], ['year' => 2013, 'month' => 8, 'day' => 22]],
            2014 => [2014, ['year' => 2014, 'month' => 8, 'day' => 16], ['year' => 2014, 'month' => 8, 'day' => 14]],
            2015 => [2015, ['year' => 2015, 'month' => 8, 'day' => 8], ['year' => 2015, 'month' => 8, 'day' => 6]],
            2016 => [2016, ['year' => 2016, 'month' => 8, 'day' => 27], ['year' => 2016, 'month' => 8, 'day' => 25]],
        ];
    }

    public function testUnknownHolidayKeyThrows(): void
    {
        $this->expectException(UnknownHolidayException::class);
        BahireHasab::getMovableHoliday('UNKNOWN_HOLIDAY', 2016);
    }
}
