<?php

namespace Antlion\SilverwindSettings;

/**
 * Hex colour helpers.
 */
class Colour
{
    public const string LIGHT_TEXT = '#ffffff';

    public const string DARK_TEXT = '#111111';

    /**
     * '#ABC', 'abc', '#AABBCC' => '#aabbcc'. Anything else => null.
     */
    public static function normalise(?string $value): ?string
    {
        $hex = ltrim(trim((string) $value), '#');

        if (preg_match('/^[0-9a-f]{3}$/i', $hex)) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return preg_match('/^[0-9a-f]{6}$/i', $hex) ? '#' . strtolower($hex) : null;
    }

    /**
     * WCAG relative luminance (0 = black, 1 = white).
     */
    public static function luminance(string $hex): float
    {
        $hex = ltrim((string) self::normalise($hex), '#');
        $channels = array_map(
            static function (string $pair): float {
                $c = hexdec($pair) / 255;
                return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
            },
            str_split($hex, 2),
        );

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    public static function contrast(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /**
     * Whichever of light/dark text has the higher contrast on $background.
     */
    public static function readableOn(string $background): string
    {
        return self::contrast($background, self::LIGHT_TEXT) >= self::contrast($background, self::DARK_TEXT)
            ? self::LIGHT_TEXT
            : self::DARK_TEXT;
    }

    public static function isDark(string $hex): bool
    {
        return self::readableOn($hex) === self::LIGHT_TEXT;
    }
}
