<?php

namespace Antlion\SilverwindSettings\Tests;

use Antlion\SilverwindSettings\Colour;
use Antlion\SilverwindSettings\ThemeSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\SiteConfig\SiteConfig;

class ThemeSettingsTest extends SapphireTest
{
    protected $usesDatabase = false;

    private function settings(array $values): ThemeSettings
    {
        return ThemeSettings::create(SiteConfig::create($values));
    }

    public function testNothingSetOutputsNothing(): void
    {
        $settings = $this->settings([]);

        $this->assertSame([], $settings->getProperties());
        $this->assertSame('', $settings->getCSS());
        $this->assertNull($settings->getFontStylesheetURL());
        $this->assertSame('', $settings->getHeadTags());
    }

    public function testBrandColoursGetReadableContentColours(): void
    {
        $props = $this->settings(['ThemePrimary' => '#005AE1', 'ThemeAccent' => 'fde047'])->getProperties();

        $this->assertSame('#005ae1', $props['--color-primary']);
        $this->assertSame('#ffffff', $props['--color-primary-content']);
        $this->assertSame('#fde047', $props['--color-accent']);
        $this->assertSame('#111111', $props['--color-accent-content']);
        $this->assertArrayNotHasKey('--color-secondary', $props);
    }

    public function testInvalidColoursAreIgnored(): void
    {
        $props = $this->settings(['ThemePrimary' => 'red;}</style><script>', 'ThemeSecondary' => '#12345'])->getProperties();

        $this->assertSame([], $props);
    }

    public function testDarkBackgroundDerivesTextSurfacesAndColourScheme(): void
    {
        $props = $this->settings(['ThemeBase' => '#0f172a'])->getProperties();

        $this->assertSame('#0f172a', $props['--color-base-100']);
        $this->assertSame('#ffffff', $props['--color-base-content']);
        $this->assertStringContainsString('color-mix', $props['--color-base-200']);
        $this->assertStringContainsString('color-mix', $props['--color-base-300']);
        $this->assertSame('dark', $props['color-scheme']);
        $this->assertArrayNotHasKey('--color-base-100-content', $props);
    }

    public function testExplicitTextColourIsKept(): void
    {
        $props = $this->settings(['ThemeBase' => '#ffffff', 'ThemeBaseContent' => '#333333'])->getProperties();

        $this->assertSame('#333333', $props['--color-base-content']);
        $this->assertSame('light', $props['color-scheme']);
        $this->assertArrayNotHasKey('--color-base-content-content', $props);
    }

    public function testFontsBuildStacksAndOneGoogleRequest(): void
    {
        $settings = $this->settings(['ThemeBodyFont' => 'Inter', 'ThemeHeadingFont' => 'Playfair Display']);
        $props = $settings->getProperties();

        $this->assertStringStartsWith('"Inter",ui-sans-serif', $props['--font-sans']);
        $this->assertStringStartsWith('"Playfair Display",ui-serif', $props['--font-heading']);
        $this->assertSame(
            'https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400..700&family=Inter:wght@400..700&display=swap',
            $settings->getFontStylesheetURL(),
        );
        $this->assertStringContainsString('&amp;family=Inter', $settings->getHeadTags());
    }

    public function testSameFontForBothIsRequestedOnce(): void
    {
        $url = $this->settings(['ThemeBodyFont' => 'Lato', 'ThemeHeadingFont' => 'Lato'])->getFontStylesheetURL();

        $this->assertSame('https://fonts.googleapis.com/css2?family=Lato:wght@400;700&display=swap', $url);
    }

    public function testUnknownFontIsIgnored(): void
    {
        $settings = $this->settings(['ThemeBodyFont' => 'Comic"};body{display:none']);

        $this->assertSame([], $settings->getProperties());
        $this->assertNull($settings->getFontStylesheetURL());
    }

    public function testPresets(): void
    {
        $props = $this->settings([
            'ThemeFontScale' => 'large',
            'ThemeContainerWidth' => 'narrow',
            'ThemeGutter' => 'default',
            'ThemeSectionSpacing' => 'relaxed',
            'ThemeCornerStyle' => 'pill',
            'ThemeBorderWidth' => 'thick',
            'ThemeFlat' => true,
            'ThemeNoise' => true,
        ])->getProperties();

        $this->assertSame([
            'font-size' => '106.25%',
            '--container-site' => '64rem',
            '--spacing-section' => '6rem',
            '--radius-box' => '1.5rem',
            '--radius-field' => '2rem',
            '--radius-selector' => '2rem',
            '--border' => '2px',
            '--depth' => '0',
            '--noise' => '1',
        ], $props);
    }

    public function testUnknownPresetsAreIgnored(): void
    {
        $props = $this->settings(['ThemeContainerWidth' => '9999px', 'ThemeCornerStyle' => 'blob'])->getProperties();

        $this->assertSame([], $props);
    }

    public function testCssUsesConfiguredSelector(): void
    {
        ThemeSettings::config()->set('selector', '[data-theme="silverwind"]');

        $css = $this->settings(['ThemePrimary' => '#000000'])->getCSS();

        $this->assertSame('[data-theme="silverwind"]{--color-primary:#000000;--color-primary-content:#ffffff}', $css);
    }

    public function testValidationRejectsBadHex(): void
    {
        $result = SiteConfig::create(['ThemePrimary' => 'blue', 'ThemeAccent' => '#abc'])->validate();

        $this->assertFalse($result->isValid());
        $fields = array_column($result->getMessages(), 'fieldName');
        $this->assertContains('ThemePrimary', $fields);
        $this->assertNotContains('ThemeAccent', $fields);
    }

    #[DataProvider('normaliseProvider')]
    public function testColourNormalise(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, Colour::normalise($input));
    }

    public static function normaliseProvider(): array
    {
        return [
            ['#AABBCC', '#aabbcc'],
            ['aabbcc', '#aabbcc'],
            ['#abc', '#aabbcc'],
            ['  #abc ', '#aabbcc'],
            ['#abcd', null],
            ['rgb(0,0,0)', null],
            ['', null],
            [null, null],
        ];
    }
}
