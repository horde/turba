<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (ASL). If you
 * did not receive this file, see http://www.horde.org/licenses/apache.
 *
 * @author Torben Dannhauer <torben@dannhauer.de>
 * @category Horde
 * @copyright 2026 The Horde Project
 * @license http://www.horde.org/licenses/apache ASL
 * @package Turba
 */

namespace Horde\Turba\Import;

/**
 * Decide when a Turba CSV/TSV import can skip the Horde_Data mapping wizard.
 *
 * Turba's own export writes composite columns (name, homeAddress, …) and
 * group helpers (kind, uid, members) that are not 1:1 destination attributes.
 * Matching scalar fields are already identical, so the mapping UI is only
 * noise for a round-trip.
 */
class ColumnMapper
{
    /**
     * CSV headers Turba writes that are not destination attributes.
     */
    public const EXPORT_ONLY = ['kind'];

    /**
     * Export names remapped to Turba internals (see data.php $import_mapping).
     *
     * @var array<string, string>
     */
    public const HEADER_ALIASES = [
        'uid' => '__uid',
        'members' => '__members',
    ];

    /**
     * Destination keys offered on the CSV mapping screen.
     *
     * Mirrors data.php: skip __* internals except uid/members, and skip
     * composite map entries (arrays).
     *
     * @param array<string, mixed> $sourceMap Address-book map from $cfgSources.
     *
     * @return list<string>
     */
    public static function destFieldKeys(array $sourceMap): array
    {
        $keys = [];
        foreach ($sourceMap as $field => $null) {
            if ((substr($field, 0, 2) != '__' && !is_array($null))
                || $field == '__uid' || $field == '__members') {
                $keys[] = $field;
            }
        }
        return $keys;
    }

    /**
     * Columns that cannot be mapped 1:1 (composites plus export-only helpers).
     *
     * @param array<string, mixed> $sourceMap
     *
     * @return list<string>
     */
    public static function ignorableColumns(array $sourceMap): array
    {
        $ignore = self::EXPORT_ONLY;
        foreach ($sourceMap as $field => $def) {
            if (is_array($def)) {
                $ignore[] = $field;
            }
        }
        return $ignore;
    }

    /**
     * Headers that identify a Turba-exported (or already-named) file.
     *
     * @param array<string, mixed> $sourceMap
     *
     * @return list<string>
     */
    public static function knownExportHeaders(array $sourceMap): array
    {
        return array_values(array_unique(array_merge(
            self::destFieldKeys($sourceMap),
            self::ignorableColumns($sourceMap),
            array_keys(self::HEADER_ALIASES),
            array_values(self::HEADER_ALIASES)
        )));
    }

    /**
     * True when leftover imported columns cannot be mapped to a real field.
     *
     * @param list<string> $csvHeaders
     * @param list<string> $destKeys
     * @param array<string, mixed> $sourceMap
     */
    public static function canSkipMapping(array $csvHeaders, array $destKeys, array $sourceMap): bool
    {
        $csvHeaders = array_values(array_filter(
            $csvHeaders,
            static fn($h) => $h !== '' && $h !== null
        ));
        if ($csvHeaders === []) {
            return false;
        }

        $destSet = array_fill_keys($destKeys, true);
        $ignorable = array_fill_keys(self::ignorableColumns($sourceMap), true);
        $matched = 0;

        foreach ($csvHeaders as $key) {
            $dest = self::resolveDestKey((string) $key, $destSet);
            if ($dest !== null) {
                $matched++;
                continue;
            }
            if (!isset($ignorable[$key])) {
                return false;
            }
        }

        return $matched > 0;
    }

    /**
     * Identity (plus uid/members alias) pairs for Horde_Data IMPORT_MAPPED.
     *
     * @param list<string> $csvHeaders
     * @param list<string> $destKeys
     *
     * @return array{0: list<string>, 1: list<string>} dataKeys, appKeys
     */
    public static function identityPairs(array $csvHeaders, array $destKeys): array
    {
        $destSet = array_fill_keys($destKeys, true);
        $dataKeys = [];
        $appKeys = [];
        foreach ($csvHeaders as $key) {
            $dest = self::resolveDestKey((string) $key, $destSet);
            if ($dest === null) {
                continue;
            }
            $dataKeys[] = (string) $key;
            $appKeys[] = $dest;
        }
        return [$dataKeys, $appKeys];
    }

    /**
     * True when the first row looks like Turba (or same-named) field headers.
     *
     * @param list<string> $headers
     * @param list<string> $knownKeys
     */
    public static function looksLikeNativeHeader(array $headers, array $knownKeys): bool
    {
        $headers = array_values(array_filter(
            $headers,
            static fn($h) => $h !== '' && $h !== null
        ));
        if (count($headers) < 3) {
            return false;
        }
        $known = array_fill_keys($knownKeys, true);
        $hits = 0;
        foreach ($headers as $h) {
            if (isset($known[$h])) {
                $hits++;
            }
        }
        return $hits >= 3 && $hits * 2 >= count($headers);
    }

    /**
     * Date attributes that still need the Horde_Data datetime wizard.
     *
     * ISO Y-m-d values from Turba's own export are left as-is.
     *
     * @param array<string, string> $timeFields app field => date|time
     * @param list<array<string, mixed>> $rows Parsed CSV rows (keys = columns).
     *
     * @return array<string, string>
     */
    public static function keepNonIsoTimeFields(array $timeFields, array $rows): array
    {
        $kept = [];
        foreach ($timeFields as $field => $type) {
            foreach ($rows as $row) {
                $val = $row[$field] ?? null;
                if ($val === null || $val === '') {
                    continue;
                }
                if (!self::isIsoDate((string) $val)) {
                    $kept[$field] = $type;
                    break;
                }
            }
        }
        return $kept;
    }

    /**
     * True when every non-empty datetime sample is already ISO (Turba export).
     *
     * @param array<string, array{type?: string, values?: list<mixed>}> $dates
     */
    public static function allDateSamplesAreIso(array $dates): bool
    {
        foreach ($dates as $date) {
            foreach ($date['values'] ?? [] as $val) {
                if ($val === null || $val === '') {
                    continue;
                }
                if (!self::isIsoDate((string) $val)) {
                    return false;
                }
            }
        }
        return $dates !== [];
    }

    /**
     * Time-field list matching data.php IMPORT_MAPPED handling.
     *
     * @param array<string, mixed> $sourceMap
     * @param array<string, array> $attributes
     *
     * @return array<string, string>
     */
    public static function timeFields(array $sourceMap, array $attributes): array
    {
        $time = [];
        foreach ($sourceMap as $field => $null) {
            if (substr($field, 0, 2) == '__' || is_array($null)) {
                continue;
            }
            switch ($attributes[$field]['type'] ?? '') {
                case 'monthyear':
                case 'monthdayyear':
                    $time[$field] = 'date';
                    break;
                case 'time':
                    $time[$field] = 'time';
                    break;
            }
        }
        return $time;
    }

    /**
     * First CSV/TSV row as header names.
     *
     * @return list<string>
     */
    public static function parseHeaderRow(string $fileData, string $sep = ',', string $quote = '"'): array
    {
        if (str_starts_with($fileData, "\xEF\xBB\xBF")) {
            $fileData = substr($fileData, 3);
        }
        $nl = strpos($fileData, "\n");
        $first = $nl === false ? $fileData : substr($fileData, 0, $nl);
        $first = rtrim($first, "\r");
        if ($first === '') {
            return [];
        }
        $row = str_getcsv($first, $sep, $quote);
        return is_array($row) ? $row : [];
    }

    public static function isIsoDate(string $val): bool
    {
        return (bool) preg_match(
            '/^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}(?::\d{2})?)?$/',
            $val
        );
    }

    /**
     * @param array<string, true> $destSet
     */
    private static function resolveDestKey(string $key, array $destSet): ?string
    {
        if (isset($destSet[$key])) {
            return $key;
        }
        if (isset(self::HEADER_ALIASES[$key]) && isset($destSet[self::HEADER_ALIASES[$key]])) {
            return self::HEADER_ALIASES[$key];
        }
        return null;
    }
}
