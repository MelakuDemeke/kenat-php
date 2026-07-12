<?php

declare(strict_types=1);

namespace Kenat;

use Kenat\Exceptions\UnknownHolidayException;

/**
 * Implements the traditional Bahire Hasab (ባሕረ ሃሳብ) algorithm.
 * `calculateBase()` is the single source of truth for `ameteAlem`, `medeb`,
 * `wenber`, `metqi`, `ninevehDate`, etc. Movable holiday dates are computed
 * as offsets ("tewsak") from the Nineveh date, then converted to Gregorian.
 *
 * Ported from kenat/src/bahireHasab.ts.
 */
final class BahireHasab
{
    private function __construct()
    {
    }

    /**
     * Calculates all Bahire Hasab values for a given Ethiopian year, including all movable feasts.
     *
     * @param array{lang?: string} $options
     * @return array{
     *     ameteAlem: int,
     *     meteneRabiet: int,
     *     evangelist: array{name: string, remainder: int},
     *     newYear: array{dayName: string, tinteQemer: int},
     *     medeb: int,
     *     wenber: int,
     *     abektie: int,
     *     metqi: int,
     *     bealeMetqi: array{date: EthiopianDate, weekday: string},
     *     mebajaHamer: int,
     *     nineveh: EthiopianDate,
     *     movableFeasts: array<string, Holiday>
     * }
     */
    public static function calculate(int $ethiopianYear, array $options = []): array
    {
        $lang = $options['lang'] ?? 'amharic';

        $base = self::calculateBase($ethiopianYear);

        $evangelistRemainder = Utils::mod($base['ameteAlem'], 4);
        $evangelistNames = Constants::evangelistNames();
        $evangelistName = $evangelistNames[$lang][$evangelistRemainder] ?? $evangelistNames['english'][$evangelistRemainder];

        $tinteQemer = Utils::mod($base['ameteAlem'] + $base['meteneRabiet'], 7);
        $weekdayIndex = Utils::mod($tinteQemer + 1, 7);
        $daysOfWeek = Constants::daysOfWeek();
        $newYearWeekday = $daysOfWeek[$lang][$weekdayIndex] ?? $daysOfWeek['english'][$weekdayIndex];

        $movableFeasts = [];
        $tewsakToKeyMap = array_flip(Constants::keyToTewsakMap());
        $holidayInfo = Constants::holidayInfo();
        $movableHolidays = Constants::movableHolidays();

        foreach (array_keys(Constants::movableHolidayTewsak()) as $tewsakKey) {
            $holidayKey = $tewsakToKeyMap[$tewsakKey] ?? null;
            if ($holidayKey === null) {
                continue;
            }

            $date = DayArithmetic::addDays($base['ninevehDate'], Constants::movableHolidayTewsak()[$tewsakKey]);
            $info = $holidayInfo[$holidayKey] ?? null;
            $rules = $movableHolidays[$holidayKey];

            $movableFeasts[$holidayKey] = new Holiday(
                key: $holidayKey,
                tags: $rules['tags'],
                movable: true,
                name: $info['name'][$lang] ?? $info['name']['english'] ?? null,
                description: $info['description'][$lang] ?? $info['description']['english'] ?? null,
                ethiopian: $date,
                gregorian: Conversions::toGC($date->year, $date->month, $date->day)
            );
        }

        return [
            'ameteAlem' => $base['ameteAlem'],
            'meteneRabiet' => $base['meteneRabiet'],
            'evangelist' => ['name' => $evangelistName, 'remainder' => $evangelistRemainder],
            'newYear' => ['dayName' => $newYearWeekday, 'tinteQemer' => $tinteQemer],
            'medeb' => $base['medeb'],
            'wenber' => $base['wenber'],
            'abektie' => $base['abektie'],
            'metqi' => $base['metqi'],
            'bealeMetqi' => ['date' => $base['bealeMetqiDate'], 'weekday' => $base['bealeMetqiWeekday']],
            'mebajaHamer' => $base['mebajaHamer'],
            'nineveh' => $base['ninevehDate'],
            'movableFeasts' => $movableFeasts,
        ];
    }

    /**
     * Calculates the date of a movable holiday for a given year.
     *
     * @throws UnknownHolidayException If the holiday key isn't in the tewsak table.
     */
    public static function getMovableHoliday(string $holidayKey, int $ethiopianYear): EthiopianDate
    {
        $tewsak = Constants::movableHolidayTewsak()[$holidayKey] ?? null;
        if ($tewsak === null) {
            throw new UnknownHolidayException($holidayKey);
        }

        $base = self::calculateBase($ethiopianYear);

        return DayArithmetic::addDays($base['ninevehDate'], $tewsak);
    }

    /**
     * Calculates and returns all base values for the Bahire Hasab system for a given
     * Ethiopian year. This is the single source of truth for the core computational logic.
     *
     * @return array{
     *     ameteAlem: int,
     *     meteneRabiet: int,
     *     medeb: int,
     *     wenber: int,
     *     abektie: int,
     *     metqi: int,
     *     bealeMetqiDate: EthiopianDate,
     *     bealeMetqiWeekday: string,
     *     mebajaHamer: int,
     *     ninevehDate: EthiopianDate
     * }
     */
    private static function calculateBase(int $ethiopianYear): array
    {
        $ameteAlem = 5500 + $ethiopianYear;
        $meteneRabiet = intdiv($ameteAlem, 4);
        $medeb = Utils::mod($ameteAlem, 19);
        $wenber = $medeb === 0 ? 18 : $medeb - 1;
        $abektie = Utils::mod($wenber * 11, 30);
        $rawMetqi = Utils::mod($wenber * 19, 30);
        $metqi = $rawMetqi === 0 ? 30 : $rawMetqi;

        $bealeMetqiMonth = $metqi > 14 ? 1 : 2;
        $bealeMetqiDay = $metqi;
        $bealeMetqiDate = new EthiopianDate($ethiopianYear, $bealeMetqiMonth, $bealeMetqiDay);

        $bealeMetqiWeekday = Constants::daysOfWeek()['english'][Utils::getWeekday($bealeMetqiDate)];
        $tewsak = Constants::tewsakMap()[$bealeMetqiWeekday];
        $mebajaHamerSum = $bealeMetqiDay + $tewsak;
        $mebajaHamer = $mebajaHamerSum > 30 ? $mebajaHamerSum % 30 : $mebajaHamerSum;

        $ninevehMonth = $metqi > 14 ? 5 : 6;
        if ($mebajaHamerSum > 30) {
            $ninevehMonth++;
        }
        $ninevehDate = new EthiopianDate($ethiopianYear, $ninevehMonth, $mebajaHamer);

        return [
            'ameteAlem' => $ameteAlem,
            'meteneRabiet' => $meteneRabiet,
            'medeb' => $medeb,
            'wenber' => $wenber,
            'abektie' => $abektie,
            'metqi' => $metqi,
            'bealeMetqiDate' => $bealeMetqiDate,
            'bealeMetqiWeekday' => $bealeMetqiWeekday,
            'mebajaHamer' => $mebajaHamer,
            'ninevehDate' => $ninevehDate,
        ];
    }
}
