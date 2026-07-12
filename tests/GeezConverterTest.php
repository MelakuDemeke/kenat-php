<?php

declare(strict_types=1);

namespace Kenat\Tests;

use Kenat\Exceptions\GeezConverterException;
use Kenat\GeezConverter;
use PHPUnit\Framework\TestCase;

/**
 * Fixtures ported from kenat/tests/geezConverter.test.ts.
 */
final class GeezConverterTest extends TestCase
{
    public function testConvertsSingleDigits(): void
    {
        $this->assertSame('፩', GeezConverter::toGeez(1));
        $this->assertSame('፪', GeezConverter::toGeez(2));
        $this->assertSame('፱', GeezConverter::toGeez(9));
    }

    public function testConvertsTens(): void
    {
        $this->assertSame('፲', GeezConverter::toGeez(10));
        $this->assertSame('፳', GeezConverter::toGeez(20));
        $this->assertSame('፺፱', GeezConverter::toGeez(99));
    }

    public function testConvertsHundreds(): void
    {
        $this->assertSame('፻', GeezConverter::toGeez(100));
        $this->assertSame('፻፩', GeezConverter::toGeez(101));
        $this->assertSame('፻፲', GeezConverter::toGeez(110));
        $this->assertSame('፻፳፫', GeezConverter::toGeez(123));
        $this->assertSame('፱፻፺፱', GeezConverter::toGeez(999));
    }

    public function testConvertsThousandsAndTenThousands(): void
    {
        $this->assertSame('፲፻', GeezConverter::toGeez(1000));
        $this->assertSame('፼', GeezConverter::toGeez(10000));
    }

    public function testZeroReturnsStringZero(): void
    {
        $this->assertSame('0', GeezConverter::toGeez(0));
    }

    public function testAcceptsStringInput(): void
    {
        $this->assertSame('፻፳፫', GeezConverter::toGeez('123'));
        $this->assertSame('፼', GeezConverter::toGeez('10000'));
    }

    public function testThrowsForNegativeInput(): void
    {
        $this->expectException(GeezConverterException::class);
        GeezConverter::toGeez(-1);
    }

    public function testThrowsForNonNumericString(): void
    {
        $this->expectException(GeezConverterException::class);
        GeezConverter::toGeez('abc');
    }

    public function testToArabicSingleDigits(): void
    {
        $this->assertSame(1, GeezConverter::toArabic('፩'));
        $this->assertSame(2, GeezConverter::toArabic('፪'));
        $this->assertSame(9, GeezConverter::toArabic('፱'));
    }

    public function testToArabicTens(): void
    {
        $this->assertSame(10, GeezConverter::toArabic('፲'));
        $this->assertSame(20, GeezConverter::toArabic('፳'));
        $this->assertSame(99, GeezConverter::toArabic('፺፱'));
    }

    public function testToArabicHundreds(): void
    {
        $this->assertSame(100, GeezConverter::toArabic('፻'));
        $this->assertSame(101, GeezConverter::toArabic('፻፩'));
        $this->assertSame(110, GeezConverter::toArabic('፻፲'));
        $this->assertSame(123, GeezConverter::toArabic('፻፳፫'));
        $this->assertSame(999, GeezConverter::toArabic('፱፻፺፱'));
    }

    public function testToArabicThousandsAndTenThousands(): void
    {
        $this->assertSame(1000, GeezConverter::toArabic('፲፻'));
        $this->assertSame(10000, GeezConverter::toArabic('፼'));
        $this->assertSame(100000, GeezConverter::toArabic('፲፼'));
    }

    public function testToArabicComplexNumbers(): void
    {
        $this->assertSame(1099, GeezConverter::toArabic('፲፻፺፱'));
    }

    public function testToArabicThrowsForUnknownNumeral(): void
    {
        $this->expectException(GeezConverterException::class);
        GeezConverter::toArabic('A');
    }

    public function testRoundTrip(): void
    {
        foreach ([1, 10, 99, 100, 123, 999, 1000, 10000, 12345, 999999] as $n) {
            $this->assertSame($n, GeezConverter::toArabic(GeezConverter::toGeez($n)), "round trip failed for {$n}");
        }
    }
}
