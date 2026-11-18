<?php

namespace Antlion\SilverwindSettings\Tokens;

use Antlion\SilverwindSettings\Colour;
use Antlion\SilverwindSettings\ThemeSettings;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Applies design tokens (a TokenFile, e.g. exported from a client's Figma
 * variables) to the Settings > Theme fields.
 *
 *   colours    copied as hex (alpha dropped, noted)
 *   fonts      must be in ThemeSettings.fonts (matched case-insensitively)
 *   sizes      snapped to the nearest preset of that setting (container
 *              width, side padding, section spacing, corners, borders,
 *              text size), compared in pixels; rounding is reported
 *
 * Which token names feed which setting is configurable (token_map), and
 * matching ignores leading groups: "Brand/Color/Primary" matches
 * "color/primary". Tokens that match nothing are listed, not an error.
 *
 *   $importer = TokenImporter::create(TokenFile::fromFile($path));
 *   $plan = $importer->plan($siteConfig);   // what would change
 *   $importer->apply($siteConfig);          // write it
 */
class TokenImporter
{
    use Configurable;

    private const int REM = 16;

    /**
     * Setting => [kind, accepted token names]. kind: colour, font, or a
     * ThemeSettings preset scale (container_widths, gutters,
     * section_spacings, corner_styles, border_widths, font_scales).
     */
    private static array $token_map = [
        'ThemePrimary' => ['colour', ['color/primary', 'colors/primary', 'brand/primary']],
        'ThemeSecondary' => ['colour', ['color/secondary', 'colors/secondary', 'brand/secondary']],
        'ThemeAccent' => ['colour', ['color/accent', 'colors/accent', 'brand/accent']],
        'ThemeNeutral' => ['colour', ['color/neutral', 'colors/neutral']],
        'ThemeBase' => ['colour', ['color/base', 'color/base-100', 'color/background', 'colors/base', 'colors/background']],
        'ThemeBaseContent' => ['colour', ['color/base-content', 'color/text', 'color/foreground', 'colors/base-content', 'colors/text']],
        'ThemeInfo' => ['colour', ['color/info', 'colors/info']],
        'ThemeSuccess' => ['colour', ['color/success', 'colors/success']],
        'ThemeWarning' => ['colour', ['color/warning', 'colors/warning']],
        'ThemeError' => ['colour', ['color/error', 'color/danger', 'colors/error']],
        'ThemeHeadingFont' => ['font', ['font/heading', 'font-family/heading', 'typography/heading', 'fonts/heading']],
        'ThemeBodyFont' => ['font', ['font/body', 'font-family/body', 'typography/body', 'fonts/body']],
        'ThemeFontScale' => ['font_scales', ['font/scale', 'type/scale', 'typography/scale', 'font/size-base', 'font-size/base']],
        'ThemeContainerWidth' => ['container_widths', ['size/container', 'layout/container', 'size/site-width', 'container/site']],
        'ThemeGutter' => ['gutters', ['size/gutter', 'spacing/gutter', 'layout/gutter']],
        'ThemeSectionSpacing' => ['section_spacings', ['size/section-spacing', 'spacing/section', 'layout/section-spacing']],
        'ThemeCornerStyle' => ['corner_styles', ['radius/box', 'radius/card', 'border-radius/box', 'radius/default']],
        'ThemeBorderWidth' => ['border_widths', ['border/width', 'border-width/default', 'size/border']],
    ];

    /**
     * What each preset scale's "default" entry means in pixels (the theme's
     * own values, see the theme's theme.css), so a token can match it.
     * Text size is a percentage.
     */
    private static array $preset_defaults = [
        'container_widths' => '80rem',
        'gutters' => '1.5rem',
        'section_spacings' => '4rem',
        'corner_styles' => '0.75rem',
        'border_widths' => '1px',
        'font_scales' => '100%',
    ];

    public function __construct(private readonly TokenFile $tokens)
    {
    }

    public static function create(TokenFile $tokens): static
    {
        return new static($tokens);
    }

    /**
     * What applying would do.
     *
     * @return array{
     *   changes: list<array{field: string, token: string, from: string, to: string, note: ?string}>,
     *   unchanged: list<string>,
     *   skipped: list<array{field: string, token: string, reason: string}>,
     *   unmatched: list<string>
     * }
     */
    public function plan(SiteConfig $config): array
    {
        $plan = ['changes' => [], 'unchanged' => [], 'skipped' => [], 'unmatched' => []];
        $used = [];

        foreach (static::config()->get('token_map') as $field => [$kind, $names]) {
            $path = $this->find($names);
            if ($path === null) {
                continue;
            }
            $used[] = $path;
            $raw = $this->tokens->all()[$path]['value'];

            [$value, $note, $error] = match ($kind) {
                'colour' => $this->colour($raw),
                'font' => $this->font($raw),
                default => $this->preset($kind, $raw),
            };

            if ($error !== null) {
                $plan['skipped'][] = ['field' => $field, 'token' => $path, 'reason' => $error];
                continue;
            }
            $current = (string) $config->getField($field);
            if ($current === $value) {
                $plan['unchanged'][] = $field;
                continue;
            }
            $plan['changes'][] = ['field' => $field, 'token' => $path, 'from' => $current, 'to' => $value, 'note' => $note];
        }

        $plan['unmatched'] = array_values(array_diff(array_keys($this->tokens->all()), $used));

        return $plan;
    }

    /** Writes the planned changes; returns the plan */
    public function apply(SiteConfig $config): array
    {
        $plan = $this->plan($config);
        foreach ($plan['changes'] as $change) {
            $config->setField($change['field'], $change['to']);
        }
        if ($plan['changes']) {
            $config->write();
        }

        return $plan;
    }

    /** First token whose path is, or ends with, one of the names */
    private function find(array $names): ?string
    {
        $paths = array_keys($this->tokens->all());
        foreach ($names as $name) {
            $name = TokenFile::normalisePath($name);
            foreach ($paths as $path) {
                if ($path === $name || str_ends_with($path, '/' . $name)) {
                    return $path;
                }
            }
        }

        return null;
    }

    // ---- Values ----------------------------------------------------------------

    /** @return array{0: string, 1: ?string, 2: ?string} [value, note, error] */
    private function colour(mixed $raw): array
    {
        $note = null;

        // DTCG colour object: {colorSpace, components, alpha, hex}
        if (is_array($raw)) {
            if (isset($raw['hex'])) {
                $hex = $raw['hex'];
            } elseif (isset($raw['components']) && count($raw['components']) >= 3
                && in_array($raw['colorSpace'] ?? 'srgb', ['srgb', 'rgb'], true)) {
                $hex = '#' . implode('', array_map(
                    fn ($c) => str_pad(dechex((int) round(max(0, min(1, (float) $c)) * 255)), 2, '0', STR_PAD_LEFT),
                    array_slice($raw['components'], 0, 3)
                ));
            } else {
                return ['', null, 'colour space not supported (use sRGB or hex)'];
            }
            if (isset($raw['alpha']) && (float) $raw['alpha'] < 1) {
                $note = 'transparency dropped';
            }
            $raw = $hex;
        }

        $raw = trim((string) $raw);
        if (preg_match('/^rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)(?:\s*,\s*([\d.]+))?\s*\)$/i', $raw, $m)) {
            $raw = sprintf('#%02x%02x%02x', min(255, (int) $m[1]), min(255, (int) $m[2]), min(255, (int) $m[3]));
            if (isset($m[4]) && (float) $m[4] < 1) {
                $note = 'transparency dropped';
            }
        }
        if (preg_match('/^#?([0-9a-f]{6})([0-9a-f]{2})$/i', $raw, $m)) {
            $raw = '#' . $m[1];
            if (strtolower($m[2]) !== 'ff') {
                $note = 'transparency dropped';
            }
        }

        $hex = Colour::normalise($raw);

        return $hex ? [$hex, $note, null] : ['', null, "not a colour: {$raw}"];
    }

    /** @return array{0: string, 1: ?string, 2: ?string} */
    private function font(mixed $raw): array
    {
        $family = is_array($raw) ? (string) ($raw['fontFamily'] ?? reset($raw)) : (string) $raw;
        // "Lora, Georgia, serif" -> "Lora"
        $family = trim(explode(',', $family)[0], " \t\"'");

        foreach (array_keys(ThemeSettings::config()->get('fonts')) as $font) {
            if (strcasecmp($font, $family) === 0) {
                return [$font, null, null];
            }
        }

        return ['', null, "font \"{$family}\" isn't in the font list (add it to ThemeSettings.fonts)"];
    }

    /** Nearest preset of a ThemeSettings scale; "default" is stored as "" */
    private function preset(string $scale, mixed $raw): array
    {
        $isPercent = $scale === 'font_scales';
        $target = $isPercent ? $this->percent($raw) : $this->pixels($raw);
        if ($target === null) {
            return ['', null, 'not a size: ' . (is_scalar($raw) ? $raw : json_encode($raw))];
        }

        $best = null;
        foreach ((array) ThemeSettings::config()->get($scale) as $key => $preset) {
            $value = $preset['value'] ?? $preset['box'] ?? '';
            if ($value === '') {
                $value = static::config()->get('preset_defaults')[$scale] ?? null;
            }
            $amount = $isPercent ? $this->percent($value) : $this->pixels($value);
            if ($amount === null) {
                continue;
            }
            // "Full width" is infinite: equal to itself, far from everything else
            $distance = is_infinite($amount) || is_infinite($target)
                ? (is_infinite($amount) && is_infinite($target) ? 0.0 : INF)
                : abs($amount - $target);
            if ($best === null || $distance < $best['distance']) {
                $best = ['key' => $key, 'distance' => $distance, 'amount' => $amount, 'label' => $preset['label'] ?? $key];
            }
        }

        if ($best === null) {
            return ['', null, "no presets for {$scale}"];
        }

        $unit = $isPercent ? '%' : 'px';
        $note = $best['distance'] > 0.5
            ? sprintf('%s%s rounded to "%s" (%s%s)', $this->number($target), $unit, $best['label'], $this->number($best['amount']), $unit)
            : null;

        return [$best['key'] === 'default' ? '' : (string) $best['key'], $note, null];
    }

    /** A dimension in px: 16, "16px", "1rem", {value, unit}, "none" (full width) */
    private function pixels(mixed $raw): ?float
    {
        if (is_array($raw) && isset($raw['value'])) {
            $raw = $raw['value'] . ($raw['unit'] ?? 'px');
        }
        if (is_int($raw) || is_float($raw)) {
            return (float) $raw;
        }
        $raw = strtolower(trim((string) $raw));
        if (in_array($raw, ['none', 'full', '100%'], true)) {
            return INF;
        }
        if (preg_match('/^(-?[\d.]+)\s*(px|rem|em)?$/', $raw, $m)) {
            $n = (float) $m[1];

            return in_array($m[2] ?? 'px', ['rem', 'em'], true) ? $n * self::REM : $n;
        }

        return null;
    }

    /**
     * Text size as a percentage: "106%", 1.0625 (ratio), 106.25, or a base
     * font size like "17px" / 17 (relative to 16px).
     */
    private function percent(mixed $raw): ?float
    {
        if (is_array($raw) && isset($raw['value'])) {
            $raw = $raw['value'] . ($raw['unit'] ?? '');
        }
        $raw = strtolower(trim((string) $raw));
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^([\d.]+)\s*%$/', $raw, $m)) {
            return (float) $m[1];
        }
        if (preg_match('/^([\d.]+)\s*(px|rem)$/', $raw, $m)) {
            $px = $m[2] === 'rem' ? (float) $m[1] * self::REM : (float) $m[1];

            return $px / self::REM * 100;
        }
        if (is_numeric($raw)) {
            $n = (float) $raw;

            return match (true) {
                $n <= 3 => $n * 100,           // ratio, e.g. 1.0625
                $n < 40 => $n / self::REM * 100, // base size in px, e.g. 17
                default => $n,                   // already a percentage
            };
        }

        return null;
    }

    private function number(float $n): string
    {
        return is_infinite($n) ? 'full' : rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    }
}
