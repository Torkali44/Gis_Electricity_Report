<?php

namespace App\Support;

final class HighlightColor
{
    public const NONE = 'none';

    public const RED = 'red';

    public const GREEN = 'green';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::NONE, self::RED, self::GREEN];
    }

    public static function normalize(?string $color): string
    {
        $color = strtolower(trim((string) $color));

        return in_array($color, self::all(), true) ? $color : self::NONE;
    }

    /** Hex fill for PDF/Word cell backgrounds (empty = no fill). Matches sample highlight. */
    public static function hexFill(string $color): ?string
    {
        return match (self::normalize($color)) {
            self::RED => 'FF0000',
            self::GREEN => '00FF00',
            default => null,
        };
    }

    /** PhpWord highlight name (fgColor). */
    public static function wordHighlight(string $color): ?string
    {
        return match (self::normalize($color)) {
            self::RED => 'red',
            self::GREEN => 'green',
            default => null,
        };
    }
}
