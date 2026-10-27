<?php

declare(strict_types=1);

namespace EggChanger\Support;

final class Ports
{
    /**
     * Parses comma separated ports and ranges such as `5520-5600, 27015`.
     *
     * @return list<array{int, int}>|null Each entry as [first, last]; null when an entry is not a port or range.
     */
    public static function ranges(string $value): ?array
    {
        $ranges = [];

        foreach (preg_split('/\s*,\s*/', mb_trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $entry) {
            if (preg_match('/^(\d{1,5})(?:\s*-\s*(\d{1,5}))?$/', $entry, $matches) !== 1) {
                return null;
            }

            $first = (int) $matches[1];
            $last = (int) ($matches[2] ?? $first);

            if ($first < 1 || $last > 65535 || $first > $last) {
                return null;
            }

            $ranges[] = [$first, $last];
        }

        return $ranges;
    }

    /** @param list<array{int, int}> $ranges */
    public static function format(array $ranges): string
    {
        return implode(', ', array_map(static fn (array $range): string => $range[0] === $range[1] ? (string) $range[0] : "{$range[0]}-{$range[1]}", $ranges));
    }

    /**
     * Whether a port lies within the ranges; no ranges allow any port.
     *
     * @param  list<array{int, int}>  $ranges
     */
    public static function allows(array $ranges, int $port): bool
    {
        if ($ranges === []) {
            return true;
        }

        foreach ($ranges as [$first, $last]) {
            if ($port >= $first && $port <= $last) {
                return true;
            }
        }

        return false;
    }
}
