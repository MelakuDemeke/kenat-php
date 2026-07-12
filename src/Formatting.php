<?php

declare(strict_types=1);

namespace Kenat;

/**
 * Pure formatting functions (standard, Geez/Amharic, with-weekday, ISO string)
 * that take Ethiopian/Gregorian date values and language/numeral options.
 *
 * Ported from kenat/src/formatting.ts.
 */
final class Formatting
{
    private function __construct()
    {
    }

    /**
     * Formats an Ethiopian date using a language-specific month name and Arabic numerals.
     * Example: "መስከረም 10 2016".
     */
    public static function formatStandard(EthiopianDate $etDate, string $lang = 'amharic'): string
    {
        $names = Constants::monthNames()[$lang] ?? Constants::monthNames()['amharic'];
        $monthName = $names[$etDate->month - 1] ?? "Month{$etDate->month}";
        return "{$monthName} {$etDate->day} {$etDate->year}";
    }

    /**
     * Formats an Ethiopian date in Geez numerals with an Amharic month name.
     * Example: "መስከረም ፲፩ ፳፻፲፮".
     */
    public static function formatInGeezAmharic(EthiopianDate $etDate): string
    {
        $monthName = Constants::monthNames()['amharic'][$etDate->month - 1] ?? "Month{$etDate->month}";
        return "{$monthName} " . GeezConverter::toGeez($etDate->day) . ' ' . GeezConverter::toGeez($etDate->year);
    }

    /**
     * Formats an Ethiopian date and time as a string.
     * Example: "መስከረም 10 2016 08:30 ጠዋት".
     */
    public static function formatWithTime(EthiopianDate $etDate, Time $time, string $lang = 'amharic'): string
    {
        $base = self::formatStandard($etDate, $lang);
        $timeString = $time->format(['lang' => $lang, 'useGeez' => false, 'zeroAsDash' => false]);
        return "{$base} {$timeString}";
    }

    /**
     * Formats an Ethiopian date with the weekday, month name, day, and year.
     * Example: "ማክሰኞ, መስከረም 1 2016".
     */
    public static function formatWithWeekday(EthiopianDate $etDate, string $lang = 'amharic', bool $useGeez = false): string
    {
        $weekdayIndex = Utils::getWeekday($etDate);
        $weekdayName = Constants::daysOfWeek()[$lang][$weekdayIndex] ?? Constants::daysOfWeek()['amharic'][$weekdayIndex];
        $monthName = Constants::monthNames()[$lang][$etDate->month - 1] ?? "Month{$etDate->month}";
        $day = $useGeez ? GeezConverter::toGeez($etDate->day) : (string) $etDate->day;
        $year = $useGeez ? GeezConverter::toGeez($etDate->year) : (string) $etDate->year;

        return "{$weekdayName}, {$monthName} {$day} {$year}";
    }

    /**
     * Formats a Gregorian date using its English month name.
     * Example: "September 11, 2023".
     */
    public static function formatGregorianStandard(GregorianDate $gDate): string
    {
        $monthName = Constants::monthNames()['gregorian'][$gDate->month - 1] ?? "Month{$gDate->month}";
        return "{$monthName} {$gDate->day}, {$gDate->year}";
    }

    /**
     * Formats a Gregorian date with the weekday, month name, day, and year.
     * Example: "Monday, September 11, 2023".
     */
    public static function formatGregorianWithWeekday(GregorianDate $gDate, string $lang = 'english'): string
    {
        $jdn = Utils::gregorianToJdn($gDate->year, $gDate->month, $gDate->day);
        $weekdayIndex = (int) Utils::mod($jdn + 1, 7);
        $weekdayName = Constants::daysOfWeek()[$lang][$weekdayIndex] ?? Constants::daysOfWeek()['english'][$weekdayIndex];
        return "{$weekdayName}, " . self::formatGregorianStandard($gDate);
    }

    /**
     * Formats a Gregorian date and time as a string.
     * Example: "September 11, 2023 08:30 ጠዋት".
     */
    public static function formatGregorianWithTime(GregorianDate $gDate, Time $time, string $lang = 'amharic'): string
    {
        $base = self::formatGregorianStandard($gDate);
        $timeString = $time->format(['lang' => $lang, 'useGeez' => false, 'zeroAsDash' => false]);
        return "{$base} {$timeString}";
    }

    /**
     * Returns an Ethiopian date in short "yyyy/mm/dd" format. Example: "2017/10/25".
     */
    public static function formatShort(EthiopianDate $etDate): string
    {
        $m = str_pad((string) $etDate->month, 2, '0', STR_PAD_LEFT);
        $d = str_pad((string) $etDate->day, 2, '0', STR_PAD_LEFT);
        return "{$etDate->year}/{$m}/{$d}";
    }

    /**
     * Returns an ISO-like string: "YYYY-MM-DD" or "YYYY-MM-DDTHH:mm".
     *
     * @param array{hour: int, minute: int, period: string}|null $time
     */
    public static function toISODateString(EthiopianDate $etDate, ?array $time = null): string
    {
        $m = str_pad((string) $etDate->month, 2, '0', STR_PAD_LEFT);
        $d = str_pad((string) $etDate->day, 2, '0', STR_PAD_LEFT);

        if ($time === null) {
            return "{$etDate->year}-{$m}-{$d}";
        }

        $hr = str_pad((string) $time['hour'], 2, '0', STR_PAD_LEFT);
        $min = str_pad((string) $time['minute'], 2, '0', STR_PAD_LEFT);
        $suffix = $time['period'] === 'night' ? '+12h' : '';

        return "{$etDate->year}-{$m}-{$d}T{$hr}:{$min}{$suffix}";
    }

    /**
     * Returns a standard ISO 8601 date-time string for a Gregorian date. Unlike
     * toISODateString(), the Ethiopian `time` (12-hour, day/night) is converted to
     * Gregorian 24-hour time via Time::toGregorian() and no non-standard suffix is
     * appended, so the result is a valid ISO 8601 string parsers can consume.
     */
    public static function toGregorianISODateString(GregorianDate $gDate, ?Time $time = null): string
    {
        $m = str_pad((string) $gDate->month, 2, '0', STR_PAD_LEFT);
        $d = str_pad((string) $gDate->day, 2, '0', STR_PAD_LEFT);

        if ($time === null) {
            return "{$gDate->year}-{$m}-{$d}";
        }

        $greg = $time->toGregorian();
        $hr = str_pad((string) $greg['hour'], 2, '0', STR_PAD_LEFT);
        $min = str_pad((string) $greg['minute'], 2, '0', STR_PAD_LEFT);

        return "{$gDate->year}-{$m}-{$d}T{$hr}:{$min}";
    }
}
