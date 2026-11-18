<?php

namespace Antlion\SilverwindSettings\Tests;

use Antlion\SilverwindSettings\Tokens\TokenFile;
use Antlion\SilverwindSettings\Tokens\TokenImporter;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\SiteConfig\SiteConfig;

class TokenImporterTest extends SapphireTest
{
    protected $usesDatabase = true;

    // ---- Token files ------------------------------------------------------------

    public function testDtcgWithGroupTypesAndReferences(): void
    {
        $file = TokenFile::fromArray([
            'Brand' => [
                '$type' => 'color',
                'Green 700' => ['$value' => '#2F6B3A'],
            ],
            'color' => [
                'primary' => ['$value' => '{Brand.Green 700}'],
            ],
        ]);

        $this->assertSame(['value' => '#2F6B3A', 'type' => 'color'], $file->all()['color/primary']);
        $this->assertArrayHasKey('brand/green-700', $file->all(), 'paths are lower-cased, spaces dashed');
    }

    public function testTokensStudioAndPlainMaps(): void
    {
        $studio = TokenFile::fromArray(['global' => ['color' => ['accent' => ['value' => '#c8963e', 'type' => 'color']]]]);
        $plain = TokenFile::fromArray(['color/accent' => '#c8963e', 'Font.Heading' => 'Lora']);

        $this->assertSame('#c8963e', $studio->all()['global/color/accent']['value']);
        $this->assertSame('#c8963e', $plain->all()['color/accent']['value']);
        $this->assertSame('Lora', $plain->all()['font/heading']['value']);
    }

    public function testReferencesWithoutTheCollectionPrefixAndCycles(): void
    {
        $file = TokenFile::fromArray([
            'Primitives' => ['blue' => ['$value' => '#005ae1']],
            'color' => [
                'primary' => ['$value' => '{blue}'],
                'loop-a' => ['$value' => '{color.loop-b}'],
                'loop-b' => ['$value' => '{color.loop-a}'],
            ],
        ]);

        $this->assertSame('#005ae1', $file->all()['color/primary']['value']);
        $this->assertStringContainsString('{', (string) $file->all()['color/loop-a']['value'], 'cycles stay unresolved, no hang');
    }

    // ---- Mapping ----------------------------------------------------------------

    public function testColoursFontsAndPresets(): void
    {
        $plan = $this->plan([
            'Fernbrook' => [
                'color' => [
                    'primary' => ['$value' => '#2F6B3A', '$type' => 'color'],
                    'accent' => ['$value' => ['colorSpace' => 'srgb', 'components' => [0.784, 0.588, 0.243], 'alpha' => 1], '$type' => 'color'],
                    'base' => ['$value' => 'rgba(255, 255, 255, 0.9)'],
                ],
                'font' => ['heading' => ['$value' => ['Lora', 'Georgia', 'serif']], 'body' => ['$value' => 'inter']],
                'size' => [
                    'container' => ['$value' => ['value' => 1440, 'unit' => 'px']],
                    'gutter' => ['$value' => '2rem'],
                    'section-spacing' => ['$value' => '64px'],
                ],
                'radius' => ['box' => ['$value' => '15px']],
                'border' => ['width' => ['$value' => 2]],
                'type' => ['scale' => ['$value' => 1.0625]],
            ],
        ]);

        $changes = array_column($plan['changes'], null, 'field');
        $this->assertSame('#2f6b3a', $changes['ThemePrimary']['to']);
        $this->assertSame('#c8963e', $changes['ThemeAccent']['to'], 'DTCG colour object');
        $this->assertSame('#ffffff', $changes['ThemeBase']['to']);
        $this->assertSame('transparency dropped', $changes['ThemeBase']['note']);
        $this->assertSame('Lora', $changes['ThemeHeadingFont']['to'], 'first family of a stack');
        $this->assertSame('Inter', $changes['ThemeBodyFont']['to'], 'matched case-insensitively');
        $this->assertSame('wide', $changes['ThemeContainerWidth']['to'], '1440px = Extra wide');
        $this->assertNull($changes['ThemeContainerWidth']['note'], 'exact match: no rounding note');
        $this->assertSame('roomy', $changes['ThemeGutter']['to']);
        $this->assertSame('thick', $changes['ThemeBorderWidth']['to']);
        $this->assertSame('large', $changes['ThemeFontScale']['to'], 'ratio 1.0625 = 106.25%');
        $this->assertSame('soft', $changes['ThemeCornerStyle']['to'], '15px is nearest Soft (16px)');
        $this->assertStringContainsString('rounded to "Soft"', $changes['ThemeCornerStyle']['note']);
        $this->assertNotContains('ThemeSectionSpacing', array_keys($changes), '64px is the default, already set');
        $this->assertContains('ThemeSectionSpacing', $plan['unchanged']);
    }

    public function testFullWidthAndDefaults(): void
    {
        $changes = array_column($this->plan(['size' => ['container' => 'none'], 'radius' => ['box' => '12px']])['changes'], 'to', 'field');

        $this->assertSame('full', $changes['ThemeContainerWidth']);
        $this->assertArrayNotHasKey('ThemeCornerStyle', $changes, '12px is the theme default: stored as "", already empty');
    }

    public function testProblemsAreReportedNotApplied(): void
    {
        $plan = $this->plan([
            'font' => ['heading' => 'Comic Neue'],
            'color' => ['primary' => 'tomato', 'secondary' => '#222222'],
            'spacing' => ['card-padding' => '24px'],
        ]);

        $skipped = array_column($plan['skipped'], 'reason', 'field');
        $this->assertStringContainsString('isn\'t in the font list', $skipped['ThemeHeadingFont']);
        $this->assertStringContainsString('not a colour', $skipped['ThemePrimary']);
        $this->assertSame(['ThemeSecondary'], array_column($plan['changes'], 'field'));
        $this->assertSame(['spacing/card-padding'], $plan['unmatched']);
    }

    public function testPlanDoesNotWriteButApplyDoes(): void
    {
        $config = SiteConfig::current_site_config();
        $importer = TokenImporter::create(TokenFile::fromArray(['color' => ['primary' => '#123456']]));

        $importer->plan($config);
        $this->assertNotSame('#123456', SiteConfig::get()->byID($config->ID)->ThemePrimary);

        $importer->apply($config);
        $this->assertSame('#123456', SiteConfig::get()->byID($config->ID)->ThemePrimary);

        $this->assertSame([], $importer->plan($config)['changes'], 'idempotent');
    }

    public function testExtraTokenNamesCanBeConfigured(): void
    {
        $map = TokenImporter::config()->get('token_map');
        $map['ThemePrimary'][1][] = 'brand/main';
        TokenImporter::config()->set('token_map', $map);

        $changes = $this->plan(['brand' => ['main' => '#0a0b0c']])['changes'];

        $this->assertSame('#0a0b0c', $changes[0]['to']);
    }

    private function plan(array $tokens): array
    {
        return TokenImporter::create(TokenFile::fromArray($tokens))->plan(SiteConfig::current_site_config());
    }
}
