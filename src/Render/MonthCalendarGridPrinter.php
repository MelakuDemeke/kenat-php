<?php

declare(strict_types=1);

namespace Kenat\Render;

use Kenat\Constants;
use Kenat\GeezConverter;

/**
 * Prints an Ethiopian month calendar grid to the console.
 *
 * Ported from kenat/src/render/printMonthCalendarGrid.ts.
 */
final class MonthCalendarGridPrinter
{
    private function __construct()
    {
    }

    /**
     * @param list<array{ethiopian: array{day:int|string}, gregorian: array{year:int,month:int,day:int}}> $calendarData
     */
    public static function print(int $ethiopianYear, int $ethiopianMonth, array $calendarData, bool $useGeez = false): void
    {
        if ($calendarData === []) {
            fwrite(STDERR, "Calendar data is empty or invalid. Cannot print grid.\n");
            return;
        }

        $daysOfWeek = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];
        $yearLabel = $useGeez ? GeezConverter::toGeez($ethiopianYear) : (string) $ethiopianYear;
        echo "\n   " . Constants::monthNames()['amharic'][$ethiopianMonth - 1] . " {$yearLabel}\n";
        echo implode('  ', $daysOfWeek) . "\n";

        $firstDayGregorian = $calendarData[0]['gregorian'];
        $weekDay = (int) ((\Kenat\Utils::gregorianToJdn($firstDayGregorian['year'], $firstDayGregorian['month'], $firstDayGregorian['day']) + 1) % 7);
        $weekDay = ($weekDay + 6) % 7; // Sunday=0 -> Monday=0.

        $row = array_fill(0, $weekDay, '    ');

        foreach ($calendarData as $day) {
            $ethDayRaw = $day['ethiopian']['day'];
            $ethDay = $useGeez
                ? str_pad(GeezConverter::toGeez((int) $ethDayRaw), 2, '፩', STR_PAD_LEFT)
                : str_pad((string) $ethDayRaw, 2, ' ', STR_PAD_LEFT);
            $gregDay = str_pad((string) $day['gregorian']['day'], 2, ' ', STR_PAD_LEFT);
            $row[] = "{$ethDay}/{$gregDay}";

            if (count($row) === 7) {
                echo implode(' ', $row) . "\n";
                $row = [];
            }
        }

        if (count($row) > 0) {
            echo str_pad(implode(' ', $row), 27, ' ') . "\n";
        }
    }
}
