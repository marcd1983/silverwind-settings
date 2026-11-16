<?php

namespace Antlion\SilverwindSettings\Extensions;

use Antlion\SilverwindSettings\Colour;
use Antlion\SilverwindSettings\ThemeSettings;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\Image;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * "Header" and "Footer" tabs inside Settings > Theme: how the site's header
 * and footer look and what they show. Content stays where it was: the logo
 * on Settings > Main, social links on Settings > Social, links in Menus.
 *
 * Every option's empty value is the theme's default, so a theme that
 * doesn't read these fields (or a site without this module) looks the same.
 *
 * @extends Extension<SiteConfig>
 */
class SiteConfigHeaderFooterExtension extends Extension
{
    private static array $db = [
        'ThemeHeaderStyle' => 'Varchar(16)',
        'ThemeHeaderBehaviour' => 'Varchar(16)',
        'ThemeHeaderWidth' => 'Varchar(16)',
        'ThemeLogoSize' => 'Varchar(16)',
        'ThemeHeaderSearch' => 'Varchar(16)',
        'ThemeMenuOpen' => 'Varchar(16)',
        'ThemeFooterStyle' => 'Varchar(16)',
        'ThemeFooterSocial' => 'Varchar(16)',
        'ThemeFooterLocations' => 'Varchar(16)',
        'ThemeFooterCopyright' => 'Varchar(255)',
    ];

    private static array $has_one = [
        'ThemeLogoOnDark' => Image::class,
    ];

    private static array $owns = ['ThemeLogoOnDark'];

    public function updateCMSFields(FieldList $fields): void
    {
        $fields->removeByName([...array_keys(self::$db), 'ThemeLogoOnDark']);

        $fields->addFieldsToTab('Root.Theme.Header', [
            DropdownField::create('ThemeHeaderStyle', _t(__CLASS__ . '.HeaderStyle', 'Style'), [
                '' => _t(__CLASS__ . '.HeaderLight', 'Light (page background)'),
                'dark' => _t(__CLASS__ . '.HeaderDark', 'Dark (neutral colour)'),
                'primary' => _t(__CLASS__ . '.HeaderPrimary', 'Brand (primary colour)'),
            ])->setDescription(_t(__CLASS__ . '.HeaderStyleDescription', 'Colours come from the Colours tab.')),
            DropdownField::create('ThemeHeaderBehaviour', _t(__CLASS__ . '.HeaderBehaviour', 'When scrolling'), [
                '' => _t(__CLASS__ . '.HeaderSticky', 'Stay at the top of the screen'),
                'static' => _t(__CLASS__ . '.HeaderStatic', 'Scroll away with the page'),
            ]),
            DropdownField::create('ThemeHeaderWidth', _t(__CLASS__ . '.HeaderWidth', 'Width'), [
                '' => _t(__CLASS__ . '.HeaderSiteWidth', 'Site width'),
                'full' => _t(__CLASS__ . '.HeaderFullWidth', 'Full width'),
            ])->setDescription(_t(__CLASS__ . '.HeaderWidthDescription', 'How far the logo and menu spread out. The header background is always full width.')),
            DropdownField::create('ThemeLogoSize', _t(__CLASS__ . '.LogoSize', 'Logo size'), [
                'sm' => _t(__CLASS__ . '.Small', 'Small'),
                '' => _t(__CLASS__ . '.Medium', 'Medium'),
                'lg' => _t(__CLASS__ . '.Large', 'Large'),
            ]),
            UploadField::create('ThemeLogoOnDark', _t(__CLASS__ . '.LogoOnDark', 'Logo for dark backgrounds'))
                ->setFolderName('Uploads/SiteLogo')
                ->setAllowedFileCategories('image/supported')
                ->setAllowedMaxFileNumber(1)
                ->setDescription(_t(__CLASS__ . '.LogoOnDarkDescription', 'Optional, usually a light or white version. Used on a dark or brand-coloured header and in the footer. The main logo is on the Main tab.')),
            DropdownField::create('ThemeHeaderSearch', _t(__CLASS__ . '.HeaderSearch', 'Search'), [
                '' => _t(__CLASS__ . '.Show', 'Show'),
                'hide' => _t(__CLASS__ . '.Hide', 'Hide'),
            ]),
            DropdownField::create('ThemeMenuOpen', _t(__CLASS__ . '.MenuOpen', 'Drop-down menus open'), [
                '' => _t(__CLASS__ . '.MenuHover', 'On hover or click'),
                'click' => _t(__CLASS__ . '.MenuClick', 'On click only'),
            ])->setDescription(_t(__CLASS__ . '.MenuOpenDescription', 'Touch screens always use click.')),
        ]);

        $fields->addFieldsToTab('Root.Theme.Footer', [
            DropdownField::create('ThemeFooterStyle', _t(__CLASS__ . '.FooterStyle', 'Style'), [
                '' => _t(__CLASS__ . '.FooterDark', 'Dark (neutral colour)'),
                'light' => _t(__CLASS__ . '.FooterLight', 'Light'),
                'primary' => _t(__CLASS__ . '.FooterPrimary', 'Brand (primary colour)'),
            ])->setDescription(_t(__CLASS__ . '.FooterStyleDescription', 'Colours come from the Colours tab.')),
            DropdownField::create('ThemeFooterSocial', _t(__CLASS__ . '.FooterSocial', 'Social links'), [
                '' => _t(__CLASS__ . '.Show', 'Show'),
                'hide' => _t(__CLASS__ . '.Hide', 'Hide'),
            ])->setDescription(_t(__CLASS__ . '.FooterSocialDescription', 'The links themselves are on the Social tab.')),
            DropdownField::create('ThemeFooterLocations', _t(__CLASS__ . '.FooterLocations', 'Contact locations'), [
                '' => _t(__CLASS__ . '.Show', 'Show'),
                'hide' => _t(__CLASS__ . '.Hide', 'Hide'),
            ])->setDescription(_t(__CLASS__ . '.FooterLocationsDescription', 'Addresses from your store location pages.')),
            TextField::create('ThemeFooterCopyright', _t(__CLASS__ . '.FooterCopyright', 'Copyright line'))
                ->setAttribute('placeholder', '© {year} ' . $this->getOwner()->Title)
                ->setDescription(_t(__CLASS__ . '.FooterCopyrightDescription', '{year} becomes the current year. Leave empty for the default shown.')),
        ]);
    }

    /** The copyright line, with {year} filled in */
    public function getThemeCopyrightText(): string
    {
        $text = trim((string) $this->getOwner()->getField('ThemeFooterCopyright'));
        if ($text === '') {
            $text = '© {year} ' . $this->getOwner()->Title;
        }

        return str_replace('{year}', DBDatetime::now()->Year(), $text);
    }

    /** Whether the header background is dark, to pick the logo for it */
    public function getThemeHeaderIsDark(): bool
    {
        return match ($this->getOwner()->getField('ThemeHeaderStyle')) {
            'dark' => true,
            'primary' => Colour::isDark($this->themeColour('Primary')),
            default => false,
        };
    }

    /** Whether the footer background is dark, to pick the logo for it */
    public function getThemeFooterIsDark(): bool
    {
        return match ($this->getOwner()->getField('ThemeFooterStyle')) {
            'light' => false,
            'primary' => Colour::isDark($this->themeColour('Primary')),
            default => Colour::isDark($this->themeColour('Neutral')),
        };
    }

    /** A theme colour as set on the Colours tab, else the theme default */
    private function themeColour(string $key): string
    {
        return Colour::normalise($this->getOwner()->getField('Theme' . $key))
            ?? ThemeSettings::config()->get('theme_defaults')[$key]
            ?? '#000000';
    }
}
