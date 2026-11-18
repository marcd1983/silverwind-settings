# Silverwind Settings

Adds a **Theme** tab to the CMS **Settings** area so editors can adjust the
[Silverwind](https://github.com/marcd1983) theme's design tokens without a rebuild:

| Tab | Settings |
|---|---|
| Colours | Primary, secondary, accent, neutral, page background and text, status colours |
| Typography | Body and heading fonts (Google Fonts), overall text size |
| Layout | Content width, side padding, section spacing, corner style, border width, flat/grain |
| Header | Style (light, dark, brand colour), stays at the top or scrolls away, width, logo size, logo for dark backgrounds, search, menus open on hover or click |
| Footer | Style (dark, light, brand colour), social links, contact locations, copyright line (`{year}` placeholder) |

Every setting is optional. Empty means "use the theme default", so a fresh
install looks exactly like the theme.

## Requirements

- Silverstripe CMS 6, PHP 8.3+
- A theme whose CSS reads daisyUI 5 / Tailwind 4 custom properties (Silverwind does)

Header and footer content stays where it already lives: the logo on
Settings > Main, social links on Settings > Social, links in the Menus admin.
The Header and Footer tabs only control how they look and what's shown.
The theme reads these fields in its templates; every empty value is the
current default, so a theme that ignores them looks the same.

## Installation

```sh
composer require antlion/silverwind-settings
vendor/bin/sake db:build --flush
```

No template changes are needed: the module adds its output to `<head>` on
every front-end page.

## Design tokens (Figma)

A client's brand can come straight from their design file. Export the
Figma variables as JSON, save it in the site as `design/tokens.json`, and:

```sh
vendor/bin/sake tasks:import-design-tokens --dry-run   # show what would change
vendor/bin/sake tasks:import-design-tokens             # apply to Settings > Theme
vendor/bin/sake tasks:import-design-tokens --file=path/to/other.json
```

It reads W3C design tokens (DTCG: Figma's variable export and most token
plugins, including colour objects and `{value, unit}` sizes), Tokens Studio
files and plain `"color/primary": "#hex"` maps, and resolves references
such as `{color.brand.500}`.

| Setting | Token names (any leading group is ignored) | Applied as |
|---|---|---|
| Brand colours | `color/primary`, `color/secondary`, `color/accent`, `color/neutral`, `color/base` (or `color/background`), `color/base-content` (or `color/text`), `color/info`, `color/success`, `color/warning`, `color/error` | hex (transparency dropped, noted) |
| Fonts | `font/heading`, `font/body` | must be in `ThemeSettings.fonts` |
| Text size | `type/scale` (`1.0625`, `106%`) or `font/size-base` (`17px`) | nearest preset |
| Site width | `size/container` (`1280px`, or `none` for full width) | nearest preset |
| Side padding, section spacing | `size/gutter`, `size/section-spacing` | nearest preset |
| Corners, borders | `radius/box`, `border/width` | nearest preset |

Sizes snap to the nearest preset, measured in pixels, and the report lists
every value it rounded, every token it couldn't use and every unknown font,
so nothing changes silently. Re-running is safe (only differences are
written), and editors can still adjust *Settings > Theme* afterwards.
`docs/example-tokens.json` is a Figma-style export to try it with.

Accept other token names in YAML:

```yaml
Antlion\SilverwindSettings\Tokens\TokenImporter:
  token_map:
    ThemePrimary: [colour, [color/primary, brand/main]]
```

## How it works

`ThemeSettings` turns the saved values into one unlayered `<style>` block:

```html
<style id="silverwind-settings">:root[data-theme]{--color-primary:#7c3aed;--color-primary-content:#ffffff;--font-sans:"Inter",ui-sans-serif,...;--radius-box:1.5rem}</style>
```

- **Only changed settings are written.** Nothing is output when everything is on its default.
- **Readable text is automatic.** Each colour gets a matching `--color-*-content` (black or white, whichever has more contrast). A custom background also derives `base-200`/`base-300` surfaces and switches `color-scheme` for dark backgrounds.
- **It wins without `!important`.** The selector `:root[data-theme]` outranks daisyUI's `[data-theme=…]` block, and unlayered CSS beats Tailwind's `@layer theme`.
- **Editors can't inject CSS.** Colours must be hex, presets must be configured keys, and fonts must be in the configured list. Anything else is ignored.

| Setting | Custom property |
|---|---|
| Colours | `--color-{primary,secondary,accent,neutral,base-100,base-content,info,success,warning,error}` (+ `-content`) |
| Body / heading font | `--font-sans`, `--font-heading` |
| Text size | `font-size` on the root (a percentage, so it respects the visitor's browser setting) |
| Content width | `--container-site` |
| Side padding | `--spacing-gutter` |
| Section spacing | `--spacing-section` |
| Corners | `--radius-box`, `--radius-field`, `--radius-selector` |
| Borders | `--border` |
| Flat / grain | `--depth: 0`, `--noise: 1` |

## Configuration

Everything lives under `Antlion\SilverwindSettings\ThemeSettings` (see
`_config/config.yml`). Override it in your project's YAML, for example to
offer another font:

```yaml
Antlion\SilverwindSettings\ThemeSettings:
  fonts:
    'Space Grotesk': { axes: 'wght@400..700', fallback: sans }
```

The `axes` value must match what the family offers in the
[Google Fonts css2 API](https://developers.google.com/fonts/docs/css2), or
Google returns a 400 and the font won't load.

`theme_defaults` only drives the CMS placeholders and the "Use default"
button. Keep it in sync with the theme's `src/css/theme.css`.

## Privacy

Choosing a font loads it from `fonts.googleapis.com`, which sends visitors'
IP addresses to Google. If that matters for your site (e.g. GDPR), leave the
fonts on the theme default or self-host them in the theme.

## Tests

```sh
vendor/bin/phpunit vendor/antlion/silverwind-settings/tests
```
# silverwind-settings
