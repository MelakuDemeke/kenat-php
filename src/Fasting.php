<?php

declare(strict_types=1);

namespace Kenat;

/**
 * Fasting period calculations (Lent, Ramadan, etc.) built on top of BahireHasab
 * and Hijri conversion.
 *
 * Ported from kenat/src/fasting.ts.
 */
final class Fasting
{
    private function __construct()
    {
    }

    /**
     * Calculates the start and end dates of a specific fasting period for a given year.
     */
    public static function getFastingPeriod(string $fastKey, int $ethiopianYear): ?DateRange
    {
        $bh = BahireHasab::calculate($ethiopianYear);
        /** @var array<string, Holiday> $movableFeasts */
        $movableFeasts = $bh['movableFeasts'];

        switch ($fastKey) {
            case FastingKeys::ABIY_TSOME:
                $start = $movableFeasts['abiyTsome']->ethiopian ?? null;
                $end = $movableFeasts['siklet']->ethiopian ?? null;
                return ($start && $end) ? new DateRange($start, $end) : null;

            case FastingKeys::TSOME_HAWARYAT:
                $start = $movableFeasts['tsomeHawaryat']->ethiopian ?? null;
                if ($start === null) {
                    return null;
                }
                return new DateRange($start, new EthiopianDate($ethiopianYear, 11, 4));

            case FastingKeys::NINEVEH:
                $start = $movableFeasts['nineveh']->ethiopian ?? null;
                if ($start === null) {
                    return null;
                }
                return new DateRange($start, DayArithmetic::addDays($start, 2));

            case FastingKeys::TSOME_NEBIYAT:
                return new DateRange(
                    new EthiopianDate($ethiopianYear, 3, 15),
                    new EthiopianDate($ethiopianYear, 4, 28)
                );

            case FastingKeys::FILSETA:
                // Nehase 1 to Nehase 14.
                return new DateRange(
                    new EthiopianDate($ethiopianYear, 12, 1),
                    new EthiopianDate($ethiopianYear, 12, 14)
                );

            case FastingKeys::RAMADAN:
                $ranges = Holidays::findHijriMonthRanges($ethiopianYear, 9);
                return $ranges[0] ?? null;

            default:
                return null;
        }
    }

    /**
     * Returns fasting information (names, descriptions, period) for a given fast and year.
     *
     * @param array{lang?: string} $options
     * @return array{key: string, name: string, description: string, tags: string[], period: ?DateRange}|null
     */
    public static function getFastingInfo(string $fastKey, int $ethiopianYear, array $options = []): ?array
    {
        $lang = $options['lang'] ?? 'amharic';
        $info = Constants::fastingInfo()[$fastKey] ?? null;
        if ($info === null) {
            return null;
        }

        $name = $info['name'][$lang] ?? $info['name']['english'];
        $description = $info['description'][$lang] ?? $info['description']['english'];

        // TSOME_DIHENET is a weekly fast (Wed/Fri) with an exception; it doesn't
        // have a single contiguous period.
        if ($fastKey === FastingKeys::TSOME_DIHENET) {
            return [
                'key' => $fastKey,
                'name' => $name,
                'description' => $description,
                'tags' => $info['tags'],
                'period' => null,
            ];
        }

        $period = self::getFastingPeriod($fastKey, $ethiopianYear);
        if ($period === null) {
            return null;
        }

        return [
            'key' => $fastKey,
            'name' => $name,
            'description' => $description,
            'tags' => $info['tags'],
            'period' => $period,
        ];
    }

    /**
     * Checks if a given Ethiopian date is an Orthodox weekly fasting day (Tsome Dihnet).
     *
     * Rules:
     * - Fasting occurs every Wednesday and Friday.
     * - Exception: for the 50 days after Easter (Fasika) up to and including Pentecost
     *   (Paraclete), Wednesdays and Fridays are NOT considered fasting days.
     */
    private static function isTsomeDihnetFastDay(EthiopianDate $etDate): bool
    {
        $weekday = Utils::getWeekday($etDate); // 0=Sun ... 6=Sat
        $isWedOrFri = ($weekday === 3 || $weekday === 5);
        if (!$isWedOrFri) {
            return false;
        }

        $bh = BahireHasab::calculate($etDate->year);
        $fasika = $bh['movableFeasts']['fasika']->ethiopian ?? null;
        $paraclete = $bh['movableFeasts']['paraclete']->ethiopian ?? null;
        if ($fasika === null || $paraclete === null) {
            // If for some reason we cannot compute the window, default to standard Wed/Fri fasting.
            return true;
        }

        $daysFromEaster = DayArithmetic::diffInDays($etDate, $fasika);
        $inPentecostSeason = $daysFromEaster >= 1 && DayArithmetic::diffInDays($etDate, $paraclete) <= 0;
        return !$inPentecostSeason;
    }

    /**
     * Returns an array of day numbers in the given Ethiopian month that belong to a fasting period.
     * For TSOME_DIHENET, returns all Wednesdays and Fridays excluding the 50-day period after
     * Easter (through Pentecost). For fixed/range fasts, returns the days intersecting the period.
     *
     * @return int[]
     */
    public static function getFastingDays(string $fastKey, int $year, int $month): array
    {
        $daysInMonth = Utils::getEthiopianDaysInMonth($year, $month);

        if ($fastKey === FastingKeys::TSOME_DIHENET) {
            $out = [];
            for ($d = 1; $d <= $daysInMonth; $d++) {
                if (self::isTsomeDihnetFastDay(new EthiopianDate($year, $month, $d))) {
                    $out[] = $d;
                }
            }
            return $out;
        }

        $period = self::getFastingPeriod($fastKey, $year);
        if ($period === null) {
            return [];
        }

        $startYearMonth = ['y' => $period->start->year, 'm' => $period->start->month];
        $endYearMonth = ['y' => $period->end->year, 'm' => $period->end->month];

        $before = ($year < $startYearMonth['y']) || ($year === $startYearMonth['y'] && $month < $startYearMonth['m']);
        $after = ($year > $endYearMonth['y']) || ($year === $endYearMonth['y'] && $month > $endYearMonth['m']);
        if ($before || $after) {
            return [];
        }

        $startDay = ($year === $period->start->year && $month === $period->start->month) ? $period->start->day : 1;
        $endDay = ($year === $period->end->year && $month === $period->end->month) ? $period->end->day : $daysInMonth;

        $result = [];
        for ($d = $startDay; $d <= $endDay; $d++) {
            $result[] = $d;
        }
        return $result;
    }
}
