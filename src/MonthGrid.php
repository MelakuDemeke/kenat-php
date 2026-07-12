<?php

declare(strict_types=1);

namespace Kenat;

use Kenat\Exceptions\InvalidGridConfigException;

/**
 * Builds calendar-grid data structures (weeks of days with Ethiopian+Gregorian
 * info, plus saints/Nigs days in 'christian' mode) used by
 * Kenat::getMonthCalendar()/getYearCalendar().
 *
 * Ported from kenat/src/MonthGrid.ts.
 */
final class MonthGrid
{
    public int $year;
    public int $month;
    public int $weekStart;
    public bool $useGeez;
    public string $weekdayLang;
    /** @var string|string[]|null */
    public string|array|null $holidayFilter;
    public ?string $mode;
    public bool $showAllSaints;

    /**
     * @param array{
     *     year?: int, month?: int, weekStart?: int, useGeez?: bool, weekdayLang?: string,
     *     holidayFilter?: string|string[]|null, mode?: ?string, showAllSaints?: bool
     * } $config
     * @throws InvalidGridConfigException
     */
    public function __construct(array $config = [])
    {
        self::validateConfig($config);

        $current = Kenat::now()->getEthiopian();
        $this->year = $config['year'] ?? $current->year;
        $this->month = $config['month'] ?? $current->month;
        $this->weekStart = $config['weekStart'] ?? 1;
        $this->useGeez = $config['useGeez'] ?? false;
        $this->weekdayLang = $config['weekdayLang'] ?? 'amharic';
        $this->holidayFilter = $config['holidayFilter'] ?? null;
        $this->mode = $config['mode'] ?? null;
        $this->showAllSaints = $config['showAllSaints'] ?? false;
    }

    /** @throws InvalidGridConfigException */
    private static function validateConfig(array $config): void
    {
        $year = $config['year'] ?? null;
        $month = $config['month'] ?? null;
        $weekStart = $config['weekStart'] ?? null;
        $weekdayLang = $config['weekdayLang'] ?? null;

        if (($year !== null && $month === null) || ($year === null && $month !== null)) {
            throw new InvalidGridConfigException('If providing year or month, both must be provided.');
        }
        if ($weekStart !== null && ($weekStart < 0 || $weekStart > 6)) {
            throw new InvalidGridConfigException("Invalid weekStart value: {$weekStart}. Must be between 0 and 6.");
        }
        if ($weekdayLang !== null) {
            $valid = array_keys(Constants::daysOfWeek());
            if (!in_array($weekdayLang, $valid, true)) {
                throw new InvalidGridConfigException(
                    "Invalid weekdayLang: \"{$weekdayLang}\". Must be one of [" . implode(', ', $valid) . '].'
                );
            }
        }
    }

    /** @param array<string, mixed> $config */
    public static function create(array $config = []): array
    {
        $instance = new self($config);
        return $instance->generate();
    }

    /**
     * @return array{
     *     headers: string[], days: array<int, ?array>, year: int|string, month: int,
     *     monthName: string, up: \Closure(): array, down: \Closure(): array
     * }
     */
    public function generate(): array
    {
        $rawDays = $this->getRawDays();
        $holidays = $this->getFilteredHolidays();
        $saints = $this->getSaintsMap();
        $paddedDays = $this->mergeDays($rawDays, $holidays, $saints);
        $headers = $this->getWeekdayHeaders();
        $monthName = $this->getLocalizedMonthName();
        $yearLabel = $this->getLocalizedYear();

        return [
            'headers' => $headers,
            'days' => $paddedDays,
            'year' => $yearLabel,
            'month' => $this->month,
            'monthName' => $monthName,
            'up' => fn () => $this->up()->generate(),
            'down' => fn () => $this->down()->generate(),
        ];
    }

    /** @return list<array{ethiopian: array, gregorian: array}> */
    private function getRawDays(): array
    {
        $base = new Kenat("{$this->year}/{$this->month}/1");
        return $base->getMonthCalendar($this->year, $this->month, $this->useGeez);
    }

    /** @return list<Holiday> */
    private function getFilteredHolidays(): array
    {
        $filter = $this->holidayFilter;
        if ($this->mode === 'christian') {
            $filter = [HolidayTags::CHRISTIAN];
        } elseif ($this->mode === 'muslim') {
            $filter = [HolidayTags::MUSLIM];
        } elseif ($this->mode === 'public') {
            $filter = [HolidayTags::PUBLIC];
        }

        return Holidays::getHolidaysInMonth($this->year, $this->month, [
            'lang' => $this->weekdayLang,
            'filter' => $filter,
        ]);
    }

    /** @return array<int, list<array<string, mixed>>> */
    private function getSaintsMap(): array
    {
        if ($this->mode !== 'christian') {
            return [];
        }

        $map = [];
        foreach (Saints::byDay() as $dayStr => $entries) {
            $day = (int) $dayStr;
            foreach ($entries as $entry) {
                $isNigs = in_array($this->month, $entry['negs'], true);
                if (!$isNigs && !$this->showAllSaints) {
                    continue;
                }

                $map[$day] ??= [];
                $map[$day][] = [
                    'key' => $entry['id'],
                    'name' => $entry['name'][$this->weekdayLang] ?? $entry['name']['english'],
                    'description' => $entry['description'][$this->weekdayLang] ?? $entry['description']['english'],
                    'isNigs' => $isNigs,
                    'tags' => [HolidayTags::RELIGIOUS, HolidayTags::CHRISTIAN, $isNigs ? 'NIGS' : 'SAINT_DAY'],
                ];
            }
        }

        return $map;
    }

    /**
     * @param list<array{ethiopian: array, gregorian: array}> $rawDays
     * @param list<Holiday> $holidaysList
     * @param array<int, list<array<string, mixed>>> $saintsMap
     * @return array<int, ?array>
     */
    private function mergeDays(array $rawDays, array $holidaysList, array $saintsMap): array
    {
        $today = Kenat::now()->getEthiopian();
        $labels = Constants::daysOfWeek()[$this->weekdayLang] ?? Constants::daysOfWeek()['amharic'];
        $monthLabels = Constants::monthNames()[$this->weekdayLang] ?? Constants::monthNames()['amharic'];

        $holidayMap = [];
        foreach ($holidaysList as $h) {
            $key = "{$h->ethiopian->year}-{$h->ethiopian->month}-{$h->ethiopian->day}";
            $holidayMap[$key] ??= [];
            $holidayMap[$key][] = $h->toArray();
        }

        $jummahInfo = Constants::holidayInfo()['jummah'];

        $mapped = [];
        foreach ($rawDays as $day) {
            $eth = $day['ethiopian'];
            $greg = $day['gregorian'];
            $ethDate = new EthiopianDate($eth['year'], $eth['month'], $eth['day']);
            $weekday = Utils::getWeekday($ethDate);
            $key = "{$eth['year']}-{$eth['month']}-{$eth['day']}";
            $holidays = $holidayMap[$key] ?? [];

            if ($this->mode === 'christian') {
                $holidays = [...$holidays, ...($saintsMap[$eth['day']] ?? [])];
            }

            if ($this->mode === 'muslim' && $weekday === 5) {
                $holidays[] = [
                    'key' => 'jummah',
                    'name' => $jummahInfo['name'][$this->weekdayLang] ?? $jummahInfo['name']['english'],
                    'description' => $jummahInfo['description'][$this->weekdayLang] ?? $jummahInfo['description']['english'],
                    'tags' => [HolidayTags::RELIGIOUS, HolidayTags::MUSLIM],
                ];
            }

            $mapped[] = [
                'ethiopian' => [
                    'year' => $this->useGeez ? GeezConverter::toGeez($eth['year']) : $eth['year'],
                    'month' => $this->useGeez ? $monthLabels[$eth['month'] - 1] : $eth['month'],
                    'day' => $this->useGeez ? GeezConverter::toGeez($eth['day']) : $eth['day'],
                ],
                'gregorian' => $greg,
                'weekday' => $weekday,
                'weekdayName' => $labels[$weekday],
                'isToday' => $eth['year'] === $today->year && $eth['month'] === $today->month && $eth['day'] === $today->day,
                'holidays' => $holidays,
            ];
        }

        $firstWeekday = count($mapped) > 0
            ? Utils::getWeekday(EthiopianDate::fromArray($rawDays[0]['ethiopian']))
            : 0;
        $offset = (int) Utils::mod($firstWeekday - $this->weekStart, 7);

        return [...array_fill(0, $offset, null), ...$mapped];
    }

    /** @return string[] */
    private function getWeekdayHeaders(): array
    {
        $labels = Constants::daysOfWeek()[$this->weekdayLang] ?? Constants::daysOfWeek()['amharic'];
        return [...array_slice($labels, $this->weekStart), ...array_slice($labels, 0, $this->weekStart)];
    }

    private function getLocalizedMonthName(): string
    {
        $labels = Constants::monthNames()[$this->weekdayLang] ?? Constants::monthNames()['amharic'];
        return $labels[$this->month - 1];
    }

    private function getLocalizedYear(): int|string
    {
        return $this->useGeez ? GeezConverter::toGeez($this->year) : $this->year;
    }

    public function up(): self
    {
        if ($this->month === 13) {
            $this->month = 1;
            $this->year++;
        } else {
            $this->month++;
        }
        return $this;
    }

    public function down(): self
    {
        if ($this->month === 1) {
            $this->month = 13;
            $this->year--;
        } else {
            $this->month--;
        }
        return $this;
    }
}
