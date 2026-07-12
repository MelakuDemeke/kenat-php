<?php

declare(strict_types=1);

namespace Kenat;

use RuntimeException;

/**
 * Loader for resources/nigs.json - Orthodox Tewahedo monthly commemorations
 * ("Nigs" days), grouped by day-of-month ("1"-"30"), each entry:
 *     {id, name: {amharic, english}, description: {amharic, english}, major, negs: [int]}
 * where `negs` lists the Ethiopian months in which that day is a major feast.
 *
 * Ported from kenat/src/nigs.json (consumed there by MonthGrid.ts in
 * 'christian' mode). Loaded lazily and cached; resolved via __DIR__ so it
 * works both from a source checkout and an installed Composer package.
 */
final class Saints
{
    /** @var array<string, list<array{id:string,name:array<string,string>,description:array<string,string>,major:bool,negs:int[]}>>|null */
    private static ?array $cache = null;

    private function __construct()
    {
    }

    /**
     * Returns the parsed nigs.json content: a map of day-of-month strings
     * ("1".."30") to a list of commemoration entries.
     *
     * @return array<string, list<array{id:string,name:array<string,string>,description:array<string,string>,major:bool,negs:int[]}>>
     */
    public static function byDay(): array
    {
        if (self::$cache === null) {
            $path = __DIR__ . '/../resources/nigs.json';
            $contents = file_get_contents($path);
            if ($contents === false) {
                throw new RuntimeException("Unable to read Nigs data file at {$path}");
            }
            /** @var array<string, list<array{id:string,name:array<string,string>,description:array<string,string>,major:bool,negs:int[]}>> $data */
            $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            self::$cache = $data;
        }

        return self::$cache;
    }
}
