<?php

declare(strict_types=1);

namespace Kenat;

use DateTimeImmutable;
use DateTimeZone;
use Kenat\Exceptions\InvalidInputTypeException;

/**
 * Combines fixed-date holidays with BahireHasab's movable feasts to answer
 * "holidays in year/month" queries; supports tag-based filtering via HolidayTags.
 *
 * Ported from kenat/src/holidays.ts.
 */
final class Holidays
{
    private function __construct()
    {
    }

    /** @return array<string, array{month: int, day: int, tags: string[]}> */
    private static function fixedHolidays(): array
    {
        return [
            'enkutatash' => ['month' => 1, 'day' => 1, 'tags' => [HolidayTags::PUBLIC, HolidayTags::CULTURAL]],
            'meskel' => ['month' => 1, 'day' => 17, 'tags' => [HolidayTags::PUBLIC, HolidayTags::RELIGIOUS, HolidayTags::CHRISTIAN]],
            'beherbehereseb' => ['month' => 3, 'day' => 29, 'tags' => [HolidayTags::PUBLIC, HolidayTags::STATE]],
            'gena' => ['month' => 4, 'day' => 29, 'tags' => [HolidayTags::PUBLIC, HolidayTags::RELIGIOUS, HolidayTags::CHRISTIAN]],
            'timket' => ['month' => 5, 'day' => 11, 'tags' => [HolidayTags::PUBLIC, HolidayTags::RELIGIOUS, HolidayTags::CHRISTIAN]],
            'martyrsDay' => ['month' => 6, 'day' => 12, 'tags' => [HolidayTags::PUBLIC, HolidayTags::STATE]],
            'adwa' => ['month' => 6, 'day' => 23, 'tags' => [HolidayTags::PUBLIC, HolidayTags::STATE]],
            'labour' => ['month' => 8, 'day' => 23, 'tags' => [HolidayTags::PUBLIC, HolidayTags::STATE]],
            'patriots' => ['month' => 8, 'day' => 27, 'tags' => [HolidayTags::PUBLIC, HolidayTags::STATE]],
        ];
    }

    /**
     * @return list<array{gregorian: GregorianDate, ethiopian: EthiopianDate}>
     */
    private static function findAllIslamicOccurrences(int $ethiopianYear, int $hijriMonth, int $hijriDay): array
    {
        $startGregorianYear = Conversions::toGC($ethiopianYear, 1, 1)->year;
        $endGregorianYear = Conversions::toGC($ethiopianYear, 13, 5)->year;
        $occurrences = [];

        $checkGregorianYear = static function (int $gYear) use (&$occurrences, $hijriMonth, $hijriDay, $ethiopianYear): void {
            $hijriYearAtStart = Conversions::getHijriYear(new DateTimeImmutable("{$gYear}-01-01", new DateTimeZone('UTC')));

            foreach ([$hijriYearAtStart, $hijriYearAtStart + 1] as $hYear) {
                $gregorianDate = Conversions::hijriToGregorian($hYear, $hijriMonth, $hijriDay, $gYear);
                if ($gregorianDate !== null && (int) $gregorianDate->format('Y') === $gYear) {
                    $ecDate = Conversions::fromDateToEC($gregorianDate);
                    if ($ecDate->year === $ethiopianYear) {
                        $occurrences[] = [
                            'gregorian' => new GregorianDate((int) $gregorianDate->format('Y'), (int) $gregorianDate->format('n'), (int) $gregorianDate->format('j')),
                            'ethiopian' => $ecDate,
                        ];
                    }
                }
            }
        };

        $checkGregorianYear($startGregorianYear);
        if ($startGregorianYear !== $endGregorianYear) {
            $checkGregorianYear($endGregorianYear);
        }

        // De-duplicate by Ethiopian date, mirroring the JS Map-keyed-by-JSON approach.
        $unique = [];
        foreach ($occurrences as $item) {
            $key = "{$item['ethiopian']->year}/{$item['ethiopian']->month}/{$item['ethiopian']->day}";
            $unique[$key] = $item;
        }

        return array_values($unique);
    }

    /**
     * Finds the start and end dates of a specific Hijri month that falls within an Ethiopian year.
     *
     * @return list<DateRange>
     */
    public static function findHijriMonthRanges(int $ethiopianYear, int $hijriMonth): array
    {
        $startGregorianYear = Conversions::toGC($ethiopianYear, 1, 1)->year;
        $endGregorianYear = Conversions::toGC($ethiopianYear, 13, 5)->year;
        $ranges = [];

        $findRangeInGregorianYear = static function (int $gYear) use (&$ranges, $hijriMonth, $ethiopianYear): void {
            $hijriYearAtStart = Conversions::getHijriYear(new DateTimeImmutable("{$gYear}-01-01", new DateTimeZone('UTC')));
            $hijriYearsToCheck = [$hijriYearAtStart - 1, $hijriYearAtStart, $hijriYearAtStart + 1];

            foreach ($hijriYearsToCheck as $hYear) {
                $startDateGregorian = Conversions::hijriToGregorian($hYear, $hijriMonth, 1, $gYear);
                if ($startDateGregorian === null) {
                    continue;
                }

                $nextMonth = $hijriMonth === 12 ? 1 : $hijriMonth + 1;
                $nextYear = $hijriMonth === 12 ? $hYear + 1 : $hYear;

                $endDateCandidate = Conversions::hijriToGregorian($nextYear, $nextMonth, 1, $gYear)
                    ?? Conversions::hijriToGregorian($nextYear, $nextMonth, 1, $gYear + 1);
                if ($endDateCandidate === null) {
                    continue;
                }
                $endDateGregorian = $endDateCandidate->modify('-1 day');

                $startEC = Conversions::fromDateToEC($startDateGregorian);
                $endEC = Conversions::fromDateToEC($endDateGregorian);

                if ($startEC->year === $ethiopianYear || $endEC->year === $ethiopianYear) {
                    $ranges[] = new DateRange($startEC, $endEC);
                }
            }
        };

        $findRangeInGregorianYear($startGregorianYear);
        if ($startGregorianYear !== $endGregorianYear) {
            $findRangeInGregorianYear($endGregorianYear);
        }

        $unique = [];
        foreach ($ranges as $range) {
            $key = "{$range->start->year}/{$range->start->month}/{$range->start->day}";
            $unique[$key] = $range;
        }

        return array_values($unique);
    }

    private static function getAllMoulidDates(int $year): array
    {
        return self::findAllIslamicOccurrences($year, 3, 12);
    }

    private static function getAllEidFitrDates(int $year): array
    {
        return self::findAllIslamicOccurrences($year, 10, 1);
    }

    private static function getAllEidAdhaDates(int $year): array
    {
        return self::findAllIslamicOccurrences($year, 12, 10);
    }

    /**
     * @param array{lang?: string} $options
     */
    public static function getHoliday(string $holidayKey, int $ethYear, array $options = []): ?Holiday
    {
        $lang = $options['lang'] ?? 'amharic';

        $info = Constants::holidayInfo()[$holidayKey] ?? null;
        if ($info === null) {
            return null;
        }

        $name = $info['name'][$lang] ?? $info['name']['english'];
        $description = $info['description'][$lang] ?? $info['description']['english'];

        $fixedHolidays = self::fixedHolidays();
        if (isset($fixedHolidays[$holidayKey])) {
            $rules = $fixedHolidays[$holidayKey];
            return new Holiday(
                key: $holidayKey,
                tags: $rules['tags'],
                movable: false,
                name: $name,
                description: $description,
                ethiopian: new EthiopianDate($ethYear, $rules['month'], $rules['day'])
            );
        }

        $tewsakKey = Constants::keyToTewsakMap()[$holidayKey] ?? null;
        if ($tewsakKey !== null) {
            $date = BahireHasab::getMovableHoliday($tewsakKey, $ethYear);
            return new Holiday(
                key: $holidayKey,
                tags: Constants::movableHolidays()[$holidayKey]['tags'],
                movable: true,
                name: $name,
                description: $description,
                ethiopian: $date,
                gregorian: Conversions::toGC($date->year, $date->month, $date->day)
            );
        }

        $muslimDateData = match ($holidayKey) {
            'eidFitr' => self::getAllEidFitrDates($ethYear)[0] ?? null,
            'eidAdha' => self::getAllEidAdhaDates($ethYear)[0] ?? null,
            'moulid' => self::getAllMoulidDates($ethYear)[0] ?? null,
            default => null,
        };

        if ($muslimDateData !== null) {
            return new Holiday(
                key: $holidayKey,
                tags: Constants::movableHolidays()[$holidayKey]['tags'],
                movable: true,
                name: $name,
                description: $description,
                ethiopian: $muslimDateData['ethiopian'],
                gregorian: $muslimDateData['gregorian']
            );
        }

        return null;
    }

    /**
     * @param array{lang?: string, filter?: string|string[]|null} $options
     * @return list<Holiday>
     * @throws InvalidInputTypeException
     */
    public static function getHolidaysInMonth(int $ethYear, int $ethMonth, array $options = []): array
    {
        if ($ethMonth < 1 || $ethMonth > 13) {
            throw new InvalidInputTypeException('getHolidaysInMonth', 'ethMonth', 'number between 1 and 13', $ethMonth);
        }

        $lang = $options['lang'] ?? 'amharic';
        $filter = $options['filter'] ?? null;

        $allHolidaysForMonth = [];
        foreach (array_keys(Constants::holidayInfo()) as $key) {
            $holiday = self::getHoliday($key, $ethYear, ['lang' => $lang]);
            if ($holiday !== null && $holiday->ethiopian->month === $ethMonth) {
                $allHolidaysForMonth[] = $holiday;
            }
        }

        $movableHolidays = Constants::movableHolidays();
        $holidayInfo = Constants::holidayInfo();

        $muslimHolidays = [];
        foreach (self::getAllMoulidDates($ethYear) as $d) {
            $muslimHolidays[] = ['key' => 'moulid', ...$d];
        }
        foreach (self::getAllEidFitrDates($ethYear) as $d) {
            $muslimHolidays[] = ['key' => 'eidFitr', ...$d];
        }
        foreach (self::getAllEidAdhaDates($ethYear) as $d) {
            $muslimHolidays[] = ['key' => 'eidAdha', ...$d];
        }

        $existingKeys = array_map(
            static fn (Holiday $h) => "{$h->ethiopian->year}-{$h->ethiopian->month}-{$h->ethiopian->day}",
            $allHolidaysForMonth
        );

        foreach ($muslimHolidays as $data) {
            if ($data['ethiopian']->month !== $ethMonth) {
                continue;
            }
            $info = $holidayInfo[$data['key']];
            $holidayObj = new Holiday(
                key: $data['key'],
                tags: $movableHolidays[$data['key']]['tags'],
                movable: true,
                name: $info['name'][$lang] ?? $info['name']['english'],
                description: $info['description'][$lang] ?? $info['description']['english'],
                ethiopian: $data['ethiopian'],
                gregorian: $data['gregorian']
            );
            $dedupeKey = "{$holidayObj->ethiopian->year}-{$holidayObj->ethiopian->month}-{$holidayObj->ethiopian->day}";
            if (!in_array($dedupeKey, $existingKeys, true)) {
                $allHolidaysForMonth[] = $holidayObj;
                $existingKeys[] = $dedupeKey;
            }
        }

        $finalHolidays = self::applyFilter($allHolidaysForMonth, $filter);

        usort($finalHolidays, static fn (Holiday $a, Holiday $b) => $a->ethiopian->day <=> $b->ethiopian->day);

        return array_values($finalHolidays);
    }

    /**
     * @param array{lang?: string, filter?: string|string[]|null} $options
     * @return list<Holiday>
     */
    public static function getHolidaysForYear(int $ethYear, array $options = []): array
    {
        $lang = $options['lang'] ?? 'amharic';
        $filter = $options['filter'] ?? null;

        $allHolidaysForYear = [];

        $singleOccurrenceKeys = [...array_keys(self::fixedHolidays()), ...array_keys(Constants::keyToTewsakMap())];
        foreach ($singleOccurrenceKeys as $key) {
            $holiday = self::getHoliday($key, $ethYear, ['lang' => $lang]);
            if ($holiday !== null) {
                $allHolidaysForYear[] = $holiday;
            }
        }

        $holidayInfo = Constants::holidayInfo();
        $movableHolidays = Constants::movableHolidays();

        $addMuslimHolidays = static function (string $key, array $dateArray) use (&$allHolidaysForYear, $holidayInfo, $movableHolidays, $lang): void {
            foreach ($dateArray as $data) {
                $info = $holidayInfo[$key];
                $allHolidaysForYear[] = new Holiday(
                    key: $key,
                    tags: $movableHolidays[$key]['tags'],
                    movable: true,
                    name: $info['name'][$lang] ?? $info['name']['english'],
                    description: $info['description'][$lang] ?? $info['description']['english'],
                    ethiopian: $data['ethiopian'],
                    gregorian: $data['gregorian']
                );
            }
        };

        $addMuslimHolidays('moulid', self::getAllMoulidDates($ethYear));
        $addMuslimHolidays('eidFitr', self::getAllEidFitrDates($ethYear));
        $addMuslimHolidays('eidAdha', self::getAllEidAdhaDates($ethYear));

        $finalHolidays = self::applyFilter($allHolidaysForYear, $filter);

        usort($finalHolidays, static function (Holiday $a, Holiday $b) {
            if ($a->ethiopian->month !== $b->ethiopian->month) {
                return $a->ethiopian->month <=> $b->ethiopian->month;
            }
            return $a->ethiopian->day <=> $b->ethiopian->day;
        });

        return array_values($finalHolidays);
    }

    /**
     * @param list<Holiday> $holidays
     * @param string|string[]|null $filter
     * @return list<Holiday>
     */
    private static function applyFilter(array $holidays, string|array|null $filter): array
    {
        if ($filter === null) {
            return $holidays;
        }
        $filterTags = is_array($filter) ? $filter : [$filter];

        return array_values(array_filter(
            $holidays,
            static fn (Holiday $holiday) => count(array_intersect($holiday->tags, $filterTags)) > 0
        ));
    }
}
