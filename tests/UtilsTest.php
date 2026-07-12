<?php

declare(strict_types=1);

namespace Kenat\Tests;

use Kenat\EthiopianDate;
use Kenat\Utils;
use PHPUnit\Framework\TestCase;

final class UtilsTest extends TestCase
{
    public function testIsGregorianLeapYear(): void
    {
        $this->assertTrue(Utils::isGregorianLeapYear(2000));
        $this->assertFalse(Utils::isGregorianLeapYear(1900));
        $this->assertTrue(Utils::isGregorianLeapYear(2024));
        $this->assertFalse(Utils::isGregorianLeapYear(2023));
    }

    public function testIsEthiopianLeapYear(): void
    {
        $this->assertTrue(Utils::isEthiopianLeapYear(2011));
        $this->assertTrue(Utils::isEthiopianLeapYear(2015));
        $this->assertFalse(Utils::isEthiopianLeapYear(2016));
    }

    public function testGetEthiopianDaysInMonth(): void
    {
        $this->assertSame(30, Utils::getEthiopianDaysInMonth(2016, 1));
        $this->assertSame(5, Utils::getEthiopianDaysInMonth(2016, 13));
        $this->assertSame(6, Utils::getEthiopianDaysInMonth(2011, 13));
    }

    public function testIsValidEthiopianDate(): void
    {
        $this->assertTrue(Utils::isValidEthiopianDate(2016, 1, 30));
        $this->assertFalse(Utils::isValidEthiopianDate(2016, 1, 31));
        $this->assertFalse(Utils::isValidEthiopianDate(2016, 14, 1));
        $this->assertTrue(Utils::isValidEthiopianDate(2011, 13, 6));
        $this->assertFalse(Utils::isValidEthiopianDate(2016, 13, 6));
    }

    /**
     * Known reference: Meskerem 1, 2016 (Sep 12, 2023) was a Tuesday.
     */
    public function testGetWeekday(): void
    {
        $weekday = Utils::getWeekday(new EthiopianDate(2016, 1, 1));
        $this->assertSame(2, $weekday); // 0=Sunday .. 2=Tuesday
    }

    public function testGregorianToJdnKnownValue(): void
    {
        // January 1, 2000 12:00 UTC is the well-known JD 2451545.0.
        $this->assertSame(2451545, Utils::gregorianToJdn(2000, 1, 1));
    }
}
