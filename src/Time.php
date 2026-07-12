<?php

declare(strict_types=1);

namespace Kenat;

use Kenat\Exceptions\InvalidTimeException;

/**
 * Models Ethiopian 12-hour day/night time, with conversion to/from Gregorian
 * 24-hour time.
 *
 * Ported from kenat/src/Time.ts.
 */
final class Time
{
    public int $hour;
    public int $minute;
    public string $period;

    /**
     * Constructs a Time instance representing an Ethiopian time.
     *
     * @param int $hour The Ethiopian hour (1-12).
     * @param int $minute The minute (0-59).
     * @param string $period 'day' or 'night'.
     * @throws InvalidTimeException If any time component is invalid.
     */
    public function __construct(int $hour, int $minute = 0, string $period = 'day')
    {
        if ($hour < 1 || $hour > 12) {
            throw new InvalidTimeException("Invalid Ethiopian hour: {$hour}. Must be between 1 and 12.");
        }
        if ($minute < 0 || $minute > 59) {
            throw new InvalidTimeException("Invalid minute: {$minute}. Must be between 0 and 59.");
        }
        if ($period !== 'day' && $period !== 'night') {
            throw new InvalidTimeException("Invalid period: \"{$period}\". Must be 'day' or 'night'.");
        }

        $this->hour = $hour;
        $this->minute = $minute;
        $this->period = $period;
    }

    /**
     * Creates a Time instance from a Gregorian 24-hour time.
     *
     * @throws InvalidTimeException If the Gregorian time is invalid.
     */
    public static function fromGregorian(int $hour, int $minute = 0): self
    {
        if ($hour < 0 || $hour > 23) {
            throw new InvalidTimeException("Invalid Gregorian hour: {$hour}. Must be between 0 and 23.");
        }
        if ($minute < 0 || $minute > 59) {
            throw new InvalidTimeException("Invalid minute: {$minute}. Must be between 0 and 59.");
        }

        // Normalize Gregorian hour to an Ethiopian base (where 6 AM is 0).
        $tempHour = $hour - 6;
        if ($tempHour < 0) {
            $tempHour += 24;
        }

        $period = $tempHour < 12 ? 'day' : 'night';
        $ethHour = $tempHour % 12;
        $ethHour = $ethHour === 0 ? 12 : $ethHour;

        return new self($ethHour, $minute, $period);
    }

    /**
     * Creates a `Time` object from a string representation.
     *
     * Expected formats:
     *   - "HH:MM" (e.g., "6:30", "፮:፴")
     *   - "HH:MM period" (e.g., "6:30 night", "፮:፴ ማታ")
     * Hour/minute accept both Arabic and Ge'ez numerals.
     *
     * @throws InvalidTimeException If the string is malformed.
     */
    public static function fromString(string $timeString): self
    {
        if (trim($timeString) === '') {
            throw new InvalidTimeException("Input must be a non-empty string, but received \"{$timeString}\".");
        }

        if (!str_contains($timeString, ':')) {
            throw new InvalidTimeException("Invalid time string format: \"{$timeString}\". Time must include a colon ':' separator.");
        }

        $parseNumber = static function (string $str): int|float {
            if (is_numeric($str)) {
                return (int) $str;
            }
            try {
                return GeezConverter::toArabic($str);
            } catch (\Throwable) {
                return NAN;
            }
        };

        $parts = array_values(array_filter(preg_split('/[:\s]+/u', $timeString) ?: []));

        if (count($parts) < 2) {
            throw new InvalidTimeException("Invalid time string format: \"{$timeString}\".");
        }

        $hour = $parseNumber($parts[0]);
        $minute = $parseNumber($parts[1]);

        if ((is_float($hour) && is_nan($hour)) || (is_float($minute) && is_nan($minute))) {
            throw new InvalidTimeException("Invalid number in time string: \"{$timeString}\"");
        }

        $period = 'day';
        if (count($parts) > 2) {
            $periodStr = mb_strtolower($parts[2]);
            if ($periodStr === 'night' || $periodStr === 'ማታ') {
                $period = 'night';
            }
        }

        return new self((int) $hour, (int) $minute, $period);
    }

    /**
     * Converts the Ethiopian time to Gregorian 24-hour format.
     *
     * @return array{hour: int, minute: int}
     */
    public function toGregorian(): array
    {
        $gregHour = $this->hour % 12;

        if ($this->period === 'day') {
            $gregHour += 6;
        } else {
            $gregHour += 18;
        }

        $gregHour %= 24;

        return ['hour' => $gregHour, 'minute' => $this->minute];
    }

    /**
     * Adds a duration to the current time.
     *
     * @param array{hours?: int, minutes?: int} $duration
     */
    public function add(array $duration): self
    {
        $hours = $duration['hours'] ?? 0;
        $minutes = $duration['minutes'] ?? 0;

        $greg = $this->toGregorian();
        $totalMinutes = $greg['hour'] * 60 + $greg['minute'] + $hours * 60 + $minutes;
        $totalMinutes = Utils::mod($totalMinutes, 1440); // Normalize to a 24-hour cycle.

        $newHour = intdiv($totalMinutes, 60);
        $newMinute = $totalMinutes % 60;

        return self::fromGregorian($newHour, $newMinute);
    }

    /**
     * Subtracts a duration from the current time.
     *
     * @param array{hours?: int, minutes?: int} $duration
     */
    public function subtract(array $duration): self
    {
        $hours = $duration['hours'] ?? 0;
        $minutes = $duration['minutes'] ?? 0;

        return $this->add(['hours' => -$hours, 'minutes' => -$minutes]);
    }

    /**
     * Calculates the absolute difference between this time and another.
     *
     * @return array{hours: int, minutes: int}
     */
    public function diff(self $otherTime): array
    {
        $t1 = $this->toGregorian();
        $t2 = $otherTime->toGregorian();
        $totalMinutes1 = $t1['hour'] * 60 + $t1['minute'];
        $totalMinutes2 = $t2['hour'] * 60 + $t2['minute'];

        $diff = abs($totalMinutes1 - $totalMinutes2);

        // Time wraps in a 24h cycle, so find the shortest path.
        if ($diff > 720) {
            $diff = 1440 - $diff;
        }

        return ['hours' => intdiv($diff, 60), 'minutes' => $diff % 60];
    }

    /**
     * Formats the time as a string.
     *
     * @param array{lang?: string, useGeez?: bool, showPeriodLabel?: bool, zeroAsDash?: bool} $options
     */
    public function format(array $options = []): string
    {
        $useGeez = $options['useGeez'] ?? true;
        // If useGeez is explicitly false, the default language should be English.
        $defaultLang = ($options['useGeez'] ?? null) === false ? 'english' : 'amharic';
        $lang = $options['lang'] ?? $defaultLang;
        $showPeriodLabel = $options['showPeriodLabel'] ?? true;
        $zeroAsDash = $options['zeroAsDash'] ?? true;

        $formatNum = static fn (int $num): string => $useGeez ? GeezConverter::toGeez($num) : str_pad((string) $num, 2, '0', STR_PAD_LEFT);

        $hourStr = $formatNum($this->hour);

        if ($zeroAsDash && $this->minute === 0) {
            $minuteStr = '_';
        } else {
            $minuteStr = $useGeez ? GeezConverter::toGeez($this->minute) : str_pad((string) $this->minute, 2, '0', STR_PAD_LEFT);
        }

        $periodLabel = '';
        if ($showPeriodLabel) {
            if ($lang === 'english') {
                $periodLabel = $this->period;
            } else {
                $periodLabel = Constants::periodLabels()[$this->period];
            }
        }

        $label = $periodLabel !== '' ? " {$periodLabel}" : '';
        return "{$hourStr}:{$minuteStr}{$label}";
    }
}
