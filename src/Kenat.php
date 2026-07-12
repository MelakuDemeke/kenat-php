<?php

declare(strict_types=1);

namespace Kenat;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Kenat\Exceptions\InvalidDateFormatException;
use Kenat\Exceptions\InvalidEthiopianDateException;
use Kenat\Exceptions\UnrecognizedInputException;
use Kenat\Render\MonthCalendarGridPrinter;

/**
 * Kenat - Ethiopian Calendar Date Wrapper.
 *
 * A lightweight class to work with both Gregorian and Ethiopian calendars.
 * Nearly all instance methods that "modify" the date return a **new** Kenat
 * instance (immutable, fluent/chainable API).
 *
 * Ported from kenat/src/Kenat.ts.
 */
final class Kenat implements \JsonSerializable
{
    public readonly EthiopianDate $ethiopian;
    public Time $time;

    /**
     * Constructs a Kenat instance. `$input` can be:
     * - null, for the current date/time.
     * - A native `DateTimeInterface` (converted from Gregorian).
     * - An `EthiopianDate` or `array{year:int,month:int,day:int}`.
     * - An Ethiopian date string (e.g. '2016/1/1', '2016-1-1').
     *
     * @param array{hour:int,minute:int,period:string}|null $timeObj
     * @throws InvalidEthiopianDateException If the provided Ethiopian date is invalid.
     * @throws InvalidDateFormatException If the provided date string format is invalid.
     * @throws UnrecognizedInputException If the input format is unrecognized.
     */
    public function __construct(
        string|array|EthiopianDate|DateTimeInterface|null $input = null,
        ?array $timeObj = null
    ) {
        if ($input === null) {
            $today = new DateTimeImmutable('now');
            $ethiopianToday = Conversions::toEC((int) $today->format('Y'), (int) $today->format('n'), (int) $today->format('j'));
            $year = $ethiopianToday->year;
            $month = $ethiopianToday->month;
            $day = $ethiopianToday->day;
            $this->time = Time::fromGregorian((int) $today->format('G'), (int) $today->format('i'));
        } elseif ($input instanceof DateTimeInterface) {
            $ethiopianDate = Conversions::toEC((int) $input->format('Y'), (int) $input->format('n'), (int) $input->format('j'));
            $year = $ethiopianDate->year;
            $month = $ethiopianDate->month;
            $day = $ethiopianDate->day;
            $this->time = Time::fromGregorian((int) $input->format('G'), (int) $input->format('i'));
        } elseif ($input instanceof EthiopianDate) {
            $year = $input->year;
            $month = $input->month;
            $day = $input->day;
            $t = $timeObj ?? ['hour' => 12, 'minute' => 0, 'period' => 'day'];
            $this->time = new Time($t['hour'], $t['minute'], $t['period']);
        } elseif (is_array($input)) {
            if (!isset($input['year'], $input['month'], $input['day'])) {
                throw new UnrecognizedInputException($input);
            }
            $year = $input['year'];
            $month = $input['month'];
            $day = $input['day'];
            $t = $timeObj ?? ['hour' => 12, 'minute' => 0, 'period' => 'day'];
            $this->time = new Time($t['hour'], $t['minute'], $t['period']);
        } elseif (is_string($input)) {
            $parts = preg_split('/[-\/]/', $input);
            if ($parts === false || count($parts) !== 3 || !self::allNumeric($parts)) {
                throw new InvalidDateFormatException($input);
            }
            [$year, $month, $day] = array_map(static fn (string $p) => (int) $p, $parts);
            $t = $timeObj ?? ['hour' => 12, 'minute' => 0, 'period' => 'day'];
            $this->time = new Time($t['hour'], $t['minute'], $t['period']);
        } else {
            throw new UnrecognizedInputException($input);
        }

        if (!Utils::isValidEthiopianDate($year, $month, $day)) {
            throw new InvalidEthiopianDateException($year, $month, $day);
        }

        $this->ethiopian = new EthiopianDate($year, $month, $day);
    }

    private static function allNumeric(array $parts): bool
    {
        foreach ($parts as $p) {
            if (!is_numeric($p)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Returns a new Kenat instance representing the current moment.
     */
    public static function now(): self
    {
        return new self();
    }

    public function getGregorian(): GregorianDate
    {
        return Conversions::toGC($this->ethiopian->year, $this->ethiopian->month, $this->ethiopian->day);
    }

    public function getEthiopian(): EthiopianDate
    {
        return $this->ethiopian;
    }

    /**
     * Returns the date in whichever calendar is requested.
     *
     * @param array{calendar?: string} $options
     */
    public function getDate(array $options = []): EthiopianDate|GregorianDate
    {
        $calendar = $options['calendar'] ?? 'ethiopian';
        return $calendar === 'gregorian' ? $this->getGregorian() : $this->getEthiopian();
    }

    /**
     * Returns a new Kenat instance with the time set.
     */
    public function setTime(int $hour, int $minute, string $period): self
    {
        $newKenat = new self($this->ethiopian);
        $newKenat->time = new Time($hour, $minute, $period);
        return $newKenat;
    }

    /**
     * Calculates the Bahire Hasab values for this instance's year.
     *
     * @return array<string, mixed>
     */
    public function getBahireHasab(): array
    {
        return BahireHasab::calculate($this->ethiopian->year);
    }

    // --- Format methods ---

    /**
     * @param array{calendar?: string} $options
     */
    public function toString(array $options = []): string
    {
        $calendar = $options['calendar'] ?? 'ethiopian';

        if ($calendar === 'gregorian') {
            return Formatting::formatGregorianWithTime($this->getGregorian(), $this->time);
        }

        return Formatting::formatWithTime($this->ethiopian, $this->time);
    }

    /**
     * @param array{calendar?: string, lang?: string, showWeekday?: bool, useGeez?: bool, includeTime?: bool} $options
     */
    public function format(array $options = []): string
    {
        $calendar = $options['calendar'] ?? 'ethiopian';
        $lang = $options['lang'] ?? 'amharic';
        $showWeekday = $options['showWeekday'] ?? false;
        $useGeez = $options['useGeez'] ?? false;
        $includeTime = $options['includeTime'] ?? false;

        if ($calendar === 'gregorian') {
            $gregorian = $this->getGregorian();

            if ($showWeekday && $includeTime) {
                return Formatting::formatGregorianWithWeekday($gregorian, $lang) . ' ' . $this->time->format(['lang' => $lang, 'useGeez' => false]);
            }
            if ($showWeekday) {
                return Formatting::formatGregorianWithWeekday($gregorian, $lang);
            }
            if ($includeTime) {
                return Formatting::formatGregorianWithTime($gregorian, $this->time, $lang);
            }
            return Formatting::formatGregorianStandard($gregorian);
        }

        if ($showWeekday && $includeTime) {
            return Formatting::formatWithWeekday($this->ethiopian, $lang, $useGeez) . ' ' . $this->time->format(['lang' => $lang, 'useGeez' => $useGeez]);
        }
        if ($showWeekday) {
            return Formatting::formatWithWeekday($this->ethiopian, $lang, $useGeez);
        }
        if ($includeTime) {
            return Formatting::formatWithTime($this->ethiopian, $this->time, $lang);
        }

        return ($useGeez && $lang === 'amharic')
            ? Formatting::formatInGeezAmharic($this->ethiopian)
            : Formatting::formatStandard($this->ethiopian, $lang);
    }

    public function formatInGeezAmharic(): string
    {
        return Formatting::formatInGeezAmharic($this->ethiopian);
    }

    public function formatWithWeekday(string $lang = 'amharic', bool $useGeez = false): string
    {
        return Formatting::formatWithWeekday($this->ethiopian, $lang, $useGeez);
    }

    public function formatShort(): string
    {
        return Formatting::formatShort($this->ethiopian);
    }

    /**
     * @param array{calendar?: string} $options
     */
    public function toISOString(array $options = []): string
    {
        $calendar = $options['calendar'] ?? 'ethiopian';

        if ($calendar === 'gregorian') {
            return Formatting::toGregorianISODateString($this->getGregorian(), $this->time);
        }

        return Formatting::toISODateString($this->ethiopian, [
            'hour' => $this->time->hour,
            'minute' => $this->time->minute,
            'period' => $this->time->period,
        ]);
    }

    /**
     * Returns the holidays that fall on this instance's date, if any.
     *
     * @param array{lang?: string} $options
     * @return list<Holiday>
     */
    public function isHoliday(array $options = []): array
    {
        $lang = $options['lang'] ?? 'amharic';
        ['year' => $year, 'month' => $month, 'day' => $day] = $this->ethiopian->toArray();

        $holidaysInMonth = Holidays::getHolidaysInMonth($year, $month, ['lang' => $lang]);

        return array_values(array_filter($holidaysInMonth, static fn (Holiday $h) => $h->ethiopian->day === $day));
    }

    /**
     * Generates a calendar for a given Ethiopian month and year, mapping each Ethiopian
     * date to its corresponding Gregorian date with display strings.
     *
     * @return list<array{ethiopian: array{year:int,month:int,day:int,display:string}, gregorian: array{year:int,month:int,day:int,display:string}}>
     */
    public function getMonthCalendar(?int $year = null, ?int $month = null, bool $useGeez = false): array
    {
        $year ??= $this->ethiopian->year;
        $month ??= $this->ethiopian->month;

        $daysInMonth = Utils::getEthiopianDaysInMonth($year, $month);
        $calendar = [];

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $gregDate = Conversions::toGC($year, $month, $day);
            $ethDisplay = $useGeez
                ? Constants::monthNames()['amharic'][$month - 1] . ' ' . GeezConverter::toGeez($day) . ' ' . GeezConverter::toGeez($year)
                : Constants::monthNames()['amharic'][$month - 1] . " {$day} {$year}";

            $calendar[] = [
                'ethiopian' => ['year' => $year, 'month' => $month, 'day' => $day, 'display' => $ethDisplay],
                'gregorian' => [
                    'year' => $gregDate->year,
                    'month' => $gregDate->month,
                    'day' => $gregDate->day,
                    'display' => sprintf('%04d-%02d-%02d', $gregDate->year, $gregDate->month, $gregDate->day),
                ],
            ];
        }

        return $calendar;
    }

    public function printThisMonth(bool $useGeez = false): void
    {
        ['year' => $year, 'month' => $month] = $this->getEthiopian()->toArray();
        $calendar = $this->getMonthCalendar($year, $month, $useGeez);
        MonthCalendarGridPrinter::print($year, $month, $calendar, $useGeez);
    }

    /**
     * @param array{useGeez?: bool, weekdayLang?: string, weekStart?: int, holidayFilter?: string|string[]|null, mode?: ?string} $options
     * @return array{month:int, monthName:string, year:int|string, headers:string[], days:array<int,?array>}
     */
    public static function getStaticMonthCalendar(int $year, int $month, array $options = []): array
    {
        $monthGrid = MonthGrid::create([
            'year' => $year,
            'month' => $month,
            'useGeez' => $options['useGeez'] ?? false,
            'weekdayLang' => $options['weekdayLang'] ?? 'amharic',
            'weekStart' => $options['weekStart'] ?? 0,
            'holidayFilter' => $options['holidayFilter'] ?? null,
            'mode' => $options['mode'] ?? 'public',
        ]);

        return [
            'month' => $month,
            'monthName' => $monthGrid['monthName'],
            'year' => $monthGrid['year'],
            'headers' => $monthGrid['headers'],
            'days' => $monthGrid['days'],
        ];
    }

    /**
     * Generates a full-year calendar as an array of 13 month objects.
     *
     * @param array{useGeez?: bool, weekdayLang?: string, weekStart?: int, holidayFilter?: string|string[]|null} $options
     * @return list<array{month:int, monthName:string, year:int|string, headers:string[], days:array<int,?array>}>
     */
    public static function getYearCalendar(int $year, array $options = []): array
    {
        $fullYear = [];

        for ($month = 1; $month <= 13; $month++) {
            $monthGrid = MonthGrid::create([
                'year' => $year,
                'month' => $month,
                'useGeez' => $options['useGeez'] ?? false,
                'weekdayLang' => $options['weekdayLang'] ?? 'amharic',
                'weekStart' => $options['weekStart'] ?? 0,
                'holidayFilter' => $options['holidayFilter'] ?? null,
            ]);

            $fullYear[] = [
                'month' => $month,
                'monthName' => $monthGrid['monthName'],
                'year' => $monthGrid['year'],
                'headers' => $monthGrid['headers'],
                'days' => $monthGrid['days'],
            ];
        }

        return $fullYear;
    }

    /**
     * Generates an array of Kenat instances for a given (inclusive) date range.
     *
     * @return list<self>
     */
    public static function generateDateRange(self $startDate, self $endDate): array
    {
        $range = [];
        $currentDate = $startDate;

        if ($startDate->isAfter($endDate)) {
            return [];
        }

        while ($currentDate->isBefore($endDate) || $currentDate->isSameDay($endDate)) {
            $range[] = $currentDate;
            $currentDate = $currentDate->addDays(1);
        }

        return $range;
    }

    // --- Arithmetic methods ---

    /**
     * Adds a specified amount of time to the current date. Supports method chaining.
     */
    public function add(int $amount, string $unit): self
    {
        $newDate = match ($unit) {
            'days' => DayArithmetic::addDays($this->ethiopian, $amount),
            'months' => DayArithmetic::addMonths($this->ethiopian, $amount),
            'years' => DayArithmetic::addYears($this->ethiopian, $amount),
            default => throw new InvalidArgumentException("Invalid unit: {$unit}. Use 'days', 'months', or 'years'"),
        };

        $newKenat = new self($newDate);
        $newKenat->time = $this->time;
        return $newKenat;
    }

    public function subtract(int $amount, string $unit): self
    {
        return $this->add(-$amount, $unit);
    }

    public function addDays(int $days): self
    {
        return $this->add($days, 'days');
    }

    public function addMonths(int $months): self
    {
        return $this->add($months, 'months');
    }

    public function addYears(int $years): self
    {
        return $this->add($years, 'years');
    }

    public function diffInDays(self $other): int
    {
        return DayArithmetic::diffInDays($this->ethiopian, $other->getEthiopian());
    }

    public function diffInMonths(self $other): int
    {
        return DayArithmetic::diffInMonths($this->ethiopian, $other->getEthiopian());
    }

    public function diffInYears(self $other): int
    {
        return DayArithmetic::diffInYears($this->ethiopian, $other->getEthiopian());
    }

    // --- Time methods ---

    public function getCurrentTime(): Time
    {
        $now = new DateTimeImmutable('now');
        return Time::fromGregorian((int) $now->format('G'), (int) $now->format('i'));
    }

    public function isBefore(self $other): bool
    {
        return $this->diffInDays($other) < 0;
    }

    public function isAfter(self $other): bool
    {
        return $this->diffInDays($other) > 0;
    }

    public function isSameDay(self $other): bool
    {
        return $this->ethiopian->equals($other->getEthiopian());
    }

    public function isSameMonth(self $other): bool
    {
        $otherEth = $other->getEthiopian();
        return $this->ethiopian->year === $otherEth->year && $this->ethiopian->month === $otherEth->month;
    }

    public function isSameYear(self $other): bool
    {
        return $this->ethiopian->year === $other->getEthiopian()->year;
    }

    /**
     * @return array{ethiopian: array{year:int,month:int,day:int}, gregorian: array{year:int,month:int,day:int}, time: array{hour:int,minute:int,period:string}|null}
     */
    public function toArray(): array
    {
        return [
            'ethiopian' => $this->ethiopian->toArray(),
            'gregorian' => $this->getGregorian()->toArray(),
            'time' => isset($this->time) ? ['hour' => $this->time->hour, 'minute' => $this->time->minute, 'period' => $this->time->period] : null,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Returns a new Kenat instance set to the start of the specified unit ('day', 'month', 'year').
     */
    public function startOf(string $unit): self
    {
        return match ($unit) {
            'day' => (function () {
                $startOfDay = new self("{$this->ethiopian->year}/{$this->ethiopian->month}/{$this->ethiopian->day}");
                $startOfDay->time = new Time(12, 0, 'day'); // 6:00 AM Gregorian.
                return $startOfDay;
            })(),
            'month' => (function () {
                $startOfMonth = new self("{$this->ethiopian->year}/{$this->ethiopian->month}/1");
                $startOfMonth->time = $this->time;
                return $startOfMonth;
            })(),
            'year' => (function () {
                $startOfYear = new self("{$this->ethiopian->year}/1/1");
                $startOfYear->time = $this->time;
                return $startOfYear;
            })(),
            default => throw new InvalidArgumentException("Invalid unit: {$unit}. Use 'day', 'month', or 'year'"),
        };
    }

    /**
     * Returns a new Kenat instance set to the end of the specified unit ('day', 'month', 'year').
     */
    public function endOf(string $unit): self
    {
        return match ($unit) {
            'day' => (function () {
                $endOfDay = new self("{$this->ethiopian->year}/{$this->ethiopian->month}/{$this->ethiopian->day}");
                $endOfDay->time = new Time(12, 0, 'night'); // 6:00 PM Gregorian.
                return $endOfDay;
            })(),
            'month' => (function () {
                $lastDay = Utils::getEthiopianDaysInMonth($this->ethiopian->year, $this->ethiopian->month);
                $endOfMonth = new self("{$this->ethiopian->year}/{$this->ethiopian->month}/{$lastDay}");
                $endOfMonth->time = $this->time;
                return $endOfMonth;
            })(),
            'year' => (function () {
                $lastDayOfPagume = Utils::getEthiopianDaysInMonth($this->ethiopian->year, 13);
                $endOfYear = new self("{$this->ethiopian->year}/13/{$lastDayOfPagume}");
                $endOfYear->time = $this->time;
                return $endOfYear;
            })(),
            default => throw new InvalidArgumentException("Invalid unit: {$unit}. Use 'day', 'month', or 'year'"),
        };
    }

    public function startOfMonth(): self
    {
        return $this->startOf('month');
    }

    public function endOfMonth(): self
    {
        return $this->endOf('month');
    }

    public function isLeapYear(): bool
    {
        return Utils::isEthiopianLeapYear($this->ethiopian->year);
    }

    public function weekday(): int
    {
        return Utils::getWeekday($this->ethiopian);
    }

    // --- Distance helpers ---

    /**
     * Returns a breakdown of distance from this date to another date.
     *
     * @param self|EthiopianDate|array{year:int,month:int,day:int}|string|DateTimeInterface $other
     * @param array{units?: string[], output?: string, lang?: string} $options
     */
    public function distanceTo(self|EthiopianDate|array|string|DateTimeInterface $other, array $options = []): DiffBreakdown|string
    {
        $target = self::coerceToKenat($other);
        $breakdown = DayArithmetic::diffBreakdown($target->getEthiopian(), $this->getEthiopian(), $options['units'] ?? ['years', 'months', 'days']);
        if (($options['output'] ?? 'object') === 'string') {
            return self::formatDistance($breakdown, $options);
        }
        return $breakdown;
    }

    /**
     * @param array{units?: string[], output?: string, lang?: string} $options
     */
    public function distanceFromToday(array $options = []): DiffBreakdown|string
    {
        $today = new self();
        return $today->distanceTo($this, $options);
    }

    /**
     * Returns distance from today to the specified holiday occurrence.
     *
     * @param array{direction?: string, units?: string[], output?: string, lang?: string} $options
     */
    public static function distanceToHoliday(string $holidayKey, array $options = []): DiffBreakdown|string
    {
        $today = new self();
        $next = self::getHolidayOccurrence($holidayKey, $today->getEthiopian(), 'next');
        $prev = self::getHolidayOccurrence($holidayKey, $today->getEthiopian(), 'prev');
        $direction = $options['direction'] ?? 'auto';

        $target = $next;
        if ($direction === 'past') {
            $target = $prev;
        }
        if ($direction === 'auto') {
            $dNext = abs(DayArithmetic::diffInDays($next->getEthiopian(), $today->getEthiopian()));
            $dPrev = abs(DayArithmetic::diffInDays($prev->getEthiopian(), $today->getEthiopian()));
            $target = $dNext <= $dPrev ? $next : $prev;
        }

        return $today->distanceTo($target, $options);
    }

    /**
     * Formats a breakdown result to a human string like "1 year 2 months 5 days".
     *
     * @param array{units?: string[], lang?: string} $options
     */
    public static function formatDistance(DiffBreakdown $breakdown, array $options = []): string
    {
        $lang = $options['lang'] ?? 'english';
        $units = $options['units'] ?? ['years', 'months', 'days'];
        $labelsEn = ['years' => 'year', 'months' => 'month', 'days' => 'day'];
        $labelsAm = ['years' => 'ዓመት', 'months' => 'ወር', 'days' => 'ቀን'];
        $labels = $lang === 'amharic' ? $labelsAm : $labelsEn;
        $plural = static fn (int $n, string $k) => $lang === 'amharic' ? $labels[$k] : ($n === 1 ? $labels[$k] : $labels[$k] . 's');

        $parts = [];
        if (in_array('years', $units, true) && $breakdown->years !== null) {
            $parts[] = "{$breakdown->years} " . $plural($breakdown->years, 'years');
        }
        if (in_array('months', $units, true) && $breakdown->months !== null) {
            $parts[] = "{$breakdown->months} " . $plural($breakdown->months, 'months');
        }
        if (in_array('days', $units, true) && $breakdown->days !== null) {
            $parts[] = "{$breakdown->days} " . $plural($breakdown->days, 'days');
        }

        return $parts !== [] ? implode(' ', $parts) : '0 ' . $plural(0, end($units));
    }

    // --- Internal helpers ---

    private static function coerceToKenat(self|EthiopianDate|array|string|DateTimeInterface $input): self
    {
        if ($input instanceof self) {
            return $input;
        }
        return new self($input);
    }

    private static function getHolidayOccurrence(string $holidayKey, EthiopianDate $refEth, string $which): self
    {
        $thisYear = Holidays::getHoliday($holidayKey, $refEth->year);
        $prevYear = Holidays::getHoliday($holidayKey, $refEth->year - 1);
        $nextYear = Holidays::getHoliday($holidayKey, $refEth->year + 1);

        $candidates = array_map(
            static fn (Holiday $h) => new self($h->ethiopian),
            array_values(array_filter([$thisYear, $prevYear, $nextYear]))
        );
        $ref = new self($refEth);

        $sorted = $candidates;
        usort($sorted, static fn (self $a, self $b) => DayArithmetic::diffInDays($a->getEthiopian(), $ref->getEthiopian()) <=> DayArithmetic::diffInDays($b->getEthiopian(), $ref->getEthiopian()));

        if ($which === 'prev') {
            $past = array_values(array_filter($candidates, static fn (self $c) => DayArithmetic::diffInDays($c->getEthiopian(), $ref->getEthiopian()) <= 0));
            usort($past, static fn (self $a, self $b) => abs(DayArithmetic::diffInDays($b->getEthiopian(), $ref->getEthiopian())) <=> abs(DayArithmetic::diffInDays($a->getEthiopian(), $ref->getEthiopian())));
            return $past[0] ?? $sorted[0];
        }

        $future = array_values(array_filter($candidates, static fn (self $c) => DayArithmetic::diffInDays($c->getEthiopian(), $ref->getEthiopian()) >= 0));
        usort($future, static fn (self $a, self $b) => abs(DayArithmetic::diffInDays($a->getEthiopian(), $ref->getEthiopian())) <=> abs(DayArithmetic::diffInDays($b->getEthiopian(), $ref->getEthiopian())));
        return $future[0] ?? $sorted[0];
    }
}
