<?php

namespace Antlion\SilverwindSettings;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Turns the Theme settings saved on SiteConfig into CSS custom properties
 * (daisyUI colour/shape tokens and the Silverwind theme's Tailwind tokens)
 * plus an optional Google Fonts stylesheet.
 *
 * Only values that pass validation reach the output: colours must be hex,
 * presets must be configured keys and fonts must be configured families,
 * so nothing an editor types can break out of the generated <style> block.
 */
class ThemeSettings
{
    use Configurable;
    use Injectable;

    public const string FONT_API = 'https://fonts.googleapis.com/css2';

    private static string $selector = ':root[data-theme]';

    private static array $theme_defaults = [];

    private static array $colour_tokens = [];

    private static array $font_scales = [];

    private static array $container_widths = [];

    private static array $gutters = [];

    private static array $section_spacings = [];

    private static array $corner_styles = [];

    private static array $border_widths = [];

    private static array $fonts = [];

    private static array $fallback_stacks = [];

    public function __construct(private readonly SiteConfig $siteConfig)
    {
    }

    /**
     * The custom properties for every setting that differs from the theme
     * default, keyed by property name.
     *
     * @return array<string, string>
     */
    public function getProperties(): array
    {
        return array_merge(
            $this->colourProperties(),
            $this->typographyProperties(),
            $this->layoutProperties(),
            $this->shapeProperties(),
        );
    }

    public function getCSS(): string
    {
        $properties = $this->getProperties();
        if (!$properties) {
            return '';
        }

        $declarations = [];
        foreach ($properties as $name => $value) {
            $declarations[] = "{$name}:{$value}";
        }

        return static::config()->get('selector') . '{' . implode(';', $declarations) . '}';
    }

    /**
     * Google Fonts css2 URL for the chosen heading/body families, or null when
     * both use the theme default.
     */
    public function getFontStylesheetURL(): ?string
    {
        $families = [];
        foreach ([$this->headingFont(), $this->bodyFont()] as $font) {
            if ($font && !isset($families[$font])) {
                $axes = static::config()->get('fonts')[$font]['axes'] ?? '';
                $families[$font] = 'family=' . str_replace(' ', '+', $font) . ($axes ? ':' . $axes : '');
            }
        }

        if (!$families) {
            return null;
        }

        return self::FONT_API . '?' . implode('&', $families) . '&display=swap';
    }

    /**
     * Everything to add to <head>: font preconnects + stylesheet, then the
     * custom-property block.
     */
    public function getHeadTags(): string
    {
        $tags = [];

        if ($url = $this->getFontStylesheetURL()) {
            $tags[] = '<link rel="preconnect" href="https://fonts.googleapis.com">';
            $tags[] = '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
            $tags[] = '<link rel="stylesheet" href="' . htmlspecialchars($url, ENT_QUOTES) . '">';
        }

        if ($css = $this->getCSS()) {
            $tags[] = '<style id="silverwind-settings">' . $css . '</style>';
        }

        return implode("\n", $tags);
    }

    private function colourProperties(): array
    {
        $properties = [];
        foreach (static::config()->get('colour_tokens') as $field => $token) {
            $hex = Colour::normalise($this->siteConfig->getField('Theme' . $field));
            if (!$hex) {
                continue;
            }
            $properties["--color-{$token}"] = $hex;
            if ($token !== 'base-content' && $token !== 'base-100') {
                $properties["--color-{$token}-content"] = Colour::readableOn($hex);
            }
        }

        // A custom page background needs matching surfaces and text: derive
        // base-200/300 from the background/text pair, pick readable text if
        // none was chosen, and switch native controls to dark when needed.
        $base = Colour::normalise($this->siteConfig->getField('ThemeBase'));
        $baseContent = Colour::normalise($this->siteConfig->getField('ThemeBaseContent'));
        if ($base || $baseContent) {
            if ($base && !$baseContent) {
                $properties['--color-base-content'] = Colour::readableOn($base);
            }
            $properties['--color-base-200'] = 'color-mix(in oklab,var(--color-base-100) 94%,var(--color-base-content))';
            $properties['--color-base-300'] = 'color-mix(in oklab,var(--color-base-100) 86%,var(--color-base-content))';
        }
        if ($base) {
            $properties['color-scheme'] = Colour::isDark($base) ? 'dark' : 'light';
        }

        return $properties;
    }

    private function typographyProperties(): array
    {
        $properties = [];

        if ($body = $this->bodyFont()) {
            $properties['--font-sans'] = $this->fontStack($body);
        }
        if ($heading = $this->headingFont()) {
            $properties['--font-heading'] = $this->fontStack($heading);
        }
        if ($scale = $this->preset('font_scales', 'ThemeFontScale')) {
            $properties['font-size'] = $scale['value'];
        }

        return $properties;
    }

    private function layoutProperties(): array
    {
        $map = [
            '--container-site' => ['container_widths', 'ThemeContainerWidth'],
            '--spacing-gutter' => ['gutters', 'ThemeGutter'],
            '--spacing-section' => ['section_spacings', 'ThemeSectionSpacing'],
        ];

        $properties = [];
        foreach ($map as $property => [$scale, $field]) {
            if ($preset = $this->preset($scale, $field)) {
                $properties[$property] = $preset['value'];
            }
        }

        return $properties;
    }

    private function shapeProperties(): array
    {
        $properties = [];

        $corners = static::config()->get('corner_styles')[$this->siteConfig->getField('ThemeCornerStyle')] ?? null;
        if ($corners) {
            foreach (['box', 'field', 'selector'] as $part) {
                if (($corners[$part] ?? '') !== '') {
                    $properties["--radius-{$part}"] = $corners[$part];
                }
            }
        }
        if ($border = $this->preset('border_widths', 'ThemeBorderWidth')) {
            $properties['--border'] = $border['value'];
        }
        if ($this->siteConfig->getField('ThemeFlat')) {
            $properties['--depth'] = '0';
        }
        if ($this->siteConfig->getField('ThemeNoise')) {
            $properties['--noise'] = '1';
        }

        return $properties;
    }

    /**
     * The configured preset for a field, or null if unset, unknown, or the
     * preset means "theme default" (empty value).
     */
    private function preset(string $scale, string $field): ?array
    {
        $preset = static::config()->get($scale)[$this->siteConfig->getField($field)] ?? null;

        return ($preset && ($preset['value'] ?? '') !== '') ? $preset : null;
    }

    private function headingFont(): ?string
    {
        return $this->configuredFont($this->siteConfig->getField('ThemeHeadingFont'));
    }

    private function bodyFont(): ?string
    {
        return $this->configuredFont($this->siteConfig->getField('ThemeBodyFont'));
    }

    private function configuredFont(?string $font): ?string
    {
        return ($font && isset(static::config()->get('fonts')[$font])) ? $font : null;
    }

    private function fontStack(string $font): string
    {
        $fallback = static::config()->get('fonts')[$font]['fallback'] ?? 'sans';
        $stack = static::config()->get('fallback_stacks')[$fallback] ?? 'sans-serif';
        $family = str_replace(['"', '\\', '<', '>', ';', '{', '}'], '', $font);

        return '"' . $family . '",' . $stack;
    }
}
