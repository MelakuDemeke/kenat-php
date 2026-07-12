<?php

declare(strict_types=1);

namespace Kenat\Tests;

use Kenat\Conversions;
use Kenat\Exceptions\InvalidGregorianDateException;
use PHPUnit\Framework\TestCase;

/**
 * Fixtures ported from kenat/tests/conversions.test.ts.
 */
final class ConversionsTest extends TestCase
{
    /**
     * @dataProvider ecToGcProvider
     */
    public function testEthiopianToGregorian(int $ey, int $em, int $ed, int $gy, int $gm, int $gd): void
    {
        $result = Conversions::toGC($ey, $em, $ed);
        $this->assertSame(['year' => $gy, 'month' => $gm, 'day' => $gd], $result->toArray());
    }

    public static function ecToGcProvider(): array
    {
        return [
            'Meskerem 1, 2016 -> Sep 12, 2023' => [2016, 1, 1, 2023, 9, 12],
            'Pagume 5, 2016 -> Sep 10, 2024' => [2016, 13, 5, 2024, 9, 10],
            'Pagume 6, 2011 (leap) -> Sep 11, 2019' => [2011, 13, 6, 2019, 9, 11],
            'Pagume 6, 2019 (leap) -> Sep 11, 2027' => [2019, 13, 6, 2027, 9, 11],
            'Miazia 27, 2016 -> May 5, 2024' => [2016, 8, 27, 2024, 5, 5],
            '2017-9-14 -> May 22, 2025' => [2017, 9, 14, 2025, 5, 22],
            'Tahsas 30, 2015 -> Jan 8, 2023' => [2015, 4, 30, 2023, 1, 8],
            'Pagume 1, 2011 -> Sep 6, 2019' => [2011, 13, 1, 2019, 9, 6],
            'Meskerem 1, 1964 (base date) -> Sep 12, 1971' => [1964, 1, 1, 1971, 9, 12],
            'Pagume 6, 2007 (leap) -> Sep 11, 2015' => [2007, 13, 6, 2015, 9, 11],
            'Pagume 5, 2006 (non-leap) -> Sep 10, 2014' => [2006, 13, 5, 2014, 9, 10],
        ];
    }

    /**
     * @dataProvider gcToEcProvider
     */
    public function testGregorianToEthiopian(int $gy, int $gm, int $gd, int $ey, int $em, int $ed): void
    {
        $result = Conversions::toEC($gy, $gm, $gd);
        $this->assertSame(['year' => $ey, 'month' => $em, 'day' => $ed], $result->toArray());
    }

    public static function gcToEcProvider(): array
    {
        return [
            'May 22, 2025 -> 2017-9-14' => [2025, 5, 22, 2017, 9, 14],
            'Feb 29, 2020 (leap) -> Yekatit 22, 2012' => [2020, 2, 29, 2012, 6, 21],
            'May 5, 2024 -> Miazia 27, 2016' => [2024, 5, 5, 2016, 8, 27],
            'Sep 10, 2024 -> Pagume 5, 2016' => [2024, 9, 10, 2016, 13, 5],
            'Sep 11, 2019 -> Pagume 6, 2011' => [2019, 9, 11, 2011, 13, 6],
            'Sep 11, 2027 -> Pagume 6, 2019' => [2027, 9, 11, 2019, 13, 6],
            'Jan 1, 2000 -> 1992-4-22' => [2000, 1, 1, 1992, 4, 22],
            'Sep 12, 1971 (base date) -> 1964-1-1' => [1971, 9, 12, 1964, 1, 1],
            'Dec 31, 2020 -> 2013-4-22' => [2020, 12, 31, 2013, 4, 22],
            'Jan 1, 2020 -> 2012-4-22' => [2020, 1, 1, 2012, 4, 22],
            'Sep 10, 2023 -> 2015-13-5' => [2023, 9, 10, 2015, 13, 5],
            'Sep 11, 2023 -> 2015-13-6' => [2023, 9, 11, 2015, 13, 6],
        ];
    }

    public function testOutOfRangeYearThrows(): void
    {
        $this->expectException(InvalidGregorianDateException::class);
        Conversions::toEC(1800, 1, 1);
    }

    public function testFutureOutOfRangeYearThrows(): void
    {
        $this->expectException(InvalidGregorianDateException::class);
        Conversions::toEC(2200, 1, 1);
    }

    public function testUpperBoundIsValid(): void
    {
        $result = Conversions::toEC(2100, 12, 31);
        $this->assertGreaterThanOrEqual(2092, $result->year);
    }

    public function testLowerBoundIsValid(): void
    {
        $result = Conversions::toEC(1900, 1, 1);
        $this->assertLessThanOrEqual(1892, $result->year);
    }

    public function testRoundTripConversion(): void
    {
        $g = Conversions::toGC(2017, 5, 12);
        $e = Conversions::toEC($g->year, $g->month, $g->day);
        $this->assertSame(['year' => 2017, 'month' => 5, 'day' => 12], $e->toArray());
    }
}
