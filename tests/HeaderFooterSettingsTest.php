<?php

namespace Antlion\SilverwindSettings\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\SiteConfig\SiteConfig;

class HeaderFooterSettingsTest extends SapphireTest
{
    protected $usesDatabase = false;

    public function testCopyrightFillsInTheYear(): void
    {
        DBDatetime::set_mock_now('2031-03-01 12:00:00');

        $this->assertSame('© 2031 Acme', SiteConfig::create(['Title' => 'Acme'])->getThemeCopyrightText(), 'Default line');
        $this->assertSame(
            'Copyright 2031 Acme Ltd. All rights reserved.',
            SiteConfig::create(['ThemeFooterCopyright' => 'Copyright {year} Acme Ltd. All rights reserved.'])->getThemeCopyrightText(),
        );

        DBDatetime::clear_mock_now();
    }

    public function testDarkBackgroundsPickTheRightLogo(): void
    {
        $this->assertFalse(SiteConfig::create()->getThemeHeaderIsDark(), 'Light header by default');
        $this->assertTrue(SiteConfig::create(['ThemeHeaderStyle' => 'dark'])->getThemeHeaderIsDark());
        $this->assertTrue(SiteConfig::create(['ThemeHeaderStyle' => 'primary'])->getThemeHeaderIsDark(), 'Default primary #005ae1 is dark');
        $this->assertFalse(SiteConfig::create(['ThemeHeaderStyle' => 'primary', 'ThemePrimary' => '#fde047'])->getThemeHeaderIsDark(), 'A light brand colour is not');

        $this->assertTrue(SiteConfig::create()->getThemeFooterIsDark(), 'Dark (neutral) footer by default');
        $this->assertFalse(SiteConfig::create(['ThemeFooterStyle' => 'light'])->getThemeFooterIsDark());
        $this->assertFalse(SiteConfig::create(['ThemeNeutral' => '#e5e7eb'])->getThemeFooterIsDark(), 'Follows a custom neutral colour');
    }

    public function testHeaderAndFooterTabsSitInsideTheme(): void
    {
        $theme = SiteConfig::create()->getCMSFields()->fieldByName('Root.Theme');
        $tabs = array_map(fn ($tab) => $tab->getName(), $theme->Tabs()->toArray());

        $this->assertSame(['Colours', 'Typography', 'Layout', 'Header', 'Footer'], $tabs);
        foreach (['ThemeHeaderStyle', 'ThemeLogoOnDark', 'ThemeMenuOpen'] as $name) {
            $this->assertNotNull($theme->fieldByName('Header')->Fields()->dataFieldByName($name));
        }
        foreach (['ThemeFooterStyle', 'ThemeFooterSocial', 'ThemeFooterLocations', 'ThemeFooterCopyright'] as $name) {
            $this->assertNotNull($theme->fieldByName('Footer')->Fields()->dataFieldByName($name));
        }
    }
}
