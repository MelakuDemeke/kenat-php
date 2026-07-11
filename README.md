# Kenat PHP / ቀናት

A modern PHP library for the Ethiopian calendar: bidirectional Ethiopian ↔ Gregorian date
conversion, Amharic/English/Geez formatting, date arithmetic, calendar grid generation, and a
full **Bahire Hasab (ባሕረ ሃሳብ)** implementation for computing movable religious holidays and
fasting periods.

This is a PHP port of [`kenat`](https://github.com/MelakuDemeke/kenat) (the JavaScript/TypeScript
original), sharing the same holiday data, Bahire Hasab algorithm, and API shape — adapted to
idiomatic, typed, modern PHP (PHP 8.1+, PSR-4, immutable value objects, zero runtime dependencies).

## Features

- **Bidirectional conversion** between Ethiopian and Gregorian calendars.
- **Calendar preference**: read or format any date as Ethiopian *or* Gregorian.
- **Complete holiday system**: public, religious (Christian & Muslim), and cultural holidays,
  filterable by tag.
- **Bahire Hasab (ባሕረ ሃሳብ)**: the traditional algorithm for movable feasts and fasts.
- **Localized formatting**: Amharic and English, including Geez numerals.
- **Full date arithmetic**: add/subtract days, months, years across the 13-month calendar.
- **Start/end of period**, date comparison, distance/diff helpers.
- **Ethiopian time**: 12-hour day/night time system, with conversion to/from Gregorian 24-hour time.
- **Calendar grid generation**: month/year grids with holiday and Orthodox saints-day overlays.
- **Fasting periods**: Lent, Nineveh, Ramadan, and more.
- **Immutable & chainable**: every method that changes a date returns a *new* `Kenat` instance.
- **Zero runtime dependencies.**

## Installation

```bash
composer require melakudemeke/kenat-php
```

Requires PHP 8.1 or later.

## Quick Start

```php
use Kenat\Kenat;

$today = new Kenat();
echo $today->format(); // e.g. "ጥቅምት 5 2018"

$date = new Kenat('2016/10/5');
echo $date->formatInGeezAmharic(); // "ሰኔ ፭ ፳፻፲፮"
echo $date->getGregorian()->year;  // 2024

$nextWeek = $date->addDays(7);
echo $nextWeek->formatShort(); // "2016/10/12"
```

## Date Conversion

```php
use Kenat\Conversions;

$gregorian = Conversions::toGC(2016, 1, 1); // GregorianDate{ year: 2023, month: 9, day: 12 }
$ethiopian = Conversions::toEC(2024, 6, 7); // EthiopianDate{ year: 2016, month: 10, day: 7 }
```

## Holidays & Bahire Hasab

```php
use Kenat\BahireHasab;
use Kenat\Holidays;
use Kenat\HolidayTags;

$bahireHasab = BahireHasab::calculate(2016);
echo $bahireHasab['evangelist']['name']; // "ዮሐንስ"
echo $bahireHasab['movableFeasts']['fasika']->ethiopian->day; // 27 (Fasika/Easter)

$publicHolidays = Holidays::getHolidaysForYear(2016, ['filter' => HolidayTags::PUBLIC]);
foreach ($publicHolidays as $holiday) {
    echo "{$holiday->name}: {$holiday->ethiopian->month}/{$holiday->ethiopian->day}\n";
}
```

## Date Arithmetic & Comparison

```php
use Kenat\Kenat;

$k = new Kenat('2015/6/10');
$result = $k->addDays(5)->addMonths(-1)->addYears(2);
// { year: 2017, month: 5, day: 15 }

$a = new Kenat('2016/06/10');
$b = new Kenat('2016/06/05');
$a->diffInDays($b); // 5
$a->isAfter($b);    // true
```

## Calendar Grid Generation

```php
use Kenat\MonthGrid;

$grid = MonthGrid::create([
    'year' => 2016,
    'month' => 1,
    'mode' => 'christian', // 'public' | 'christian' | 'muslim' | null
    'weekdayLang' => 'amharic',
]);

echo $grid['monthName']; // "መስከረም"
foreach ($grid['days'] as $day) {
    if ($day === null) continue; // leading padding cell
    // $day['holidays'] includes fixed/movable holidays and, in 'christian'
    // mode, Orthodox monthly commemorations (Nigs days).
}
```

## Ethiopian Time

```php
use Kenat\Time;

$time = Time::fromGregorian(17, 30);
echo $time->format(); // "፲፩:፴ ጠዋት"

$time->toGregorian(); // ['hour' => 17, 'minute' => 30]
```

## Fasting Periods

```php
use Kenat\Fasting;
use Kenat\FastingKeys;

$period = Fasting::getFastingPeriod(FastingKeys::ABIY_TSOME, 2016);
echo "{$period->start->month}/{$period->start->day} - {$period->end->month}/{$period->end->day}";
```

## Design Notes

- **Value objects, not arrays**: `EthiopianDate`, `GregorianDate`, `Holiday`, `DateRange`, and
  `DiffBreakdown` are immutable, typed, `JsonSerializable` value objects rather than loose
  associative arrays, so IDEs and static analysis can follow the data through the API.
- **Static utility classes** (`Conversions`, `DayArithmetic`, `Formatting`, `GeezConverter`,
  `Holidays`, `BahireHasab`, `Fasting`, `Utils`) operate on those value objects — mirroring the
  original library's "pure functions operate on plain dates; `Kenat` is a thin stateful wrapper"
  architecture.
- **Islamic calendar dates** (Eid al-Fitr, Eid al-Adha, Moulid, Ramadan) are computed with a
  self-contained tabular Islamic calendar (Julian-Day-Number based), since PHP has no built-in
  equivalent to the JS original's ICU `islamic` calendar binding without requiring `ext-intl`.
  This can disagree with observation-based Islamic calendars by a day at some month boundaries —
  a known, accepted limitation, consistent with the project's other ports.
- **Orthodox saints data** (`resources/nigs.json`) is copied verbatim from the upstream `kenat`
  project and consumed by `MonthGrid` in `'christian'` mode.

## Testing

```bash
composer install
composer test
```

## License

MIT — see [LICENSE](LICENSE).

## Related Projects

- [`kenat`](https://github.com/MelakuDemeke/kenat) — the original JavaScript/TypeScript library.
- [`kenat_py`](https://github.com/MelakuDemeke/kenat_py) — Python port.
- [`kenat-dart`](https://github.com/MelakuDemeke/kenat-dart) — Dart port.
