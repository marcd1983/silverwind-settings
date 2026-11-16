<?php

namespace Antlion\SilverwindSettings\Extensions;

use Antlion\SilverwindSettings\Colour;
use Antlion\SilverwindSettings\Forms\ThemeColorField;
use Antlion\SilverwindSettings\ThemeSettings;
use SilverStripe\Core\Extension;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\ToggleCompositeField;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Adds a "Theme" tab to Settings with colour, typography, layout and shape
 * options. Every field is optional: empty means "use the theme default".
 * See ThemeSettings for how the values become CSS.
 *
 * @extends Extension<SiteConfig>
 */
class SiteConfigThemeExtension extends Extension
{
    private static array $db = [
        'ThemePrimary' => 'Varchar(7)',
        'ThemeSecondary' => 'Varchar(7)',
        'ThemeAccent' => 'Varchar(7)',
        'ThemeNeutral' => 'Varchar(7)',
        'ThemeBase' => 'Varchar(7)',
        'ThemeBaseContent' => 'Varchar(7)',
        'ThemeInfo' => 'Varchar(7)',
        'ThemeSuccess' => 'Varchar(7)',
        'ThemeWarning' => 'Varchar(7)',
        'ThemeError' => 'Varchar(7)',
        'ThemeHeadingFont' => 'Varchar(64)',
        'ThemeBodyFont' => 'Varchar(64)',
        'ThemeFontScale' => 'Varchar(32)',
        'ThemeContainerWidth' => 'Varchar(32)',
        'ThemeGutter' => 'Varchar(32)',
        'ThemeSectionSpacing' => 'Varchar(32)',
        'ThemeCornerStyle' => 'Varchar(32)',
        'ThemeBorderWidth' => 'Varchar(32)',
        'ThemeFlat' => 'Boolean',
        'ThemeNoise' => 'Boolean',
    ];

    public function updateCMSFields(FieldList $fields): void
    {
        $fields->removeByName(array_keys(self::$db));

        $fields->addFieldsToTab('Root.Theme.Colours', [
            LiteralField::create('ThemeColoursIntro', '<p class="form__field-description">'
                . _t(__CLASS__ . '.ColoursIntro', 'Leave a colour empty to use the theme default. Text placed on each colour is chosen automatically for contrast.')
                . '</p>'),
            HeaderField::create('ThemeBrandHeader', _t(__CLASS__ . '.Brand', 'Brand'), 3),
            $this->colourField('Primary', _t(__CLASS__ . '.Primary', 'Primary'), _t(__CLASS__ . '.PrimaryDescription', 'Main buttons, links and highlights.')),
            $this->colourField('Secondary', _t(__CLASS__ . '.Secondary', 'Secondary')),
            $this->colourField('Accent', _t(__CLASS__ . '.Accent', 'Accent')),
            $this->colourField('Neutral', _t(__CLASS__ . '.Neutral', 'Neutral'), _t(__CLASS__ . '.NeutralDescription', 'Footer and other dark surfaces.')),
            HeaderField::create('ThemePageHeader', _t(__CLASS__ . '.Page', 'Page'), 3),
            $this->colourField('Base', _t(__CLASS__ . '.Base', 'Background')),
            $this->colourField('BaseContent', _t(__CLASS__ . '.BaseContent', 'Text'), _t(__CLASS__ . '.BaseContentDescription', 'Picked automatically from the background if left empty.')),
            ToggleCompositeField::create('ThemeStatusColours', _t(__CLASS__ . '.Status', 'Status colours'), [
                $this->colourField('Info', _t(__CLASS__ . '.Info', 'Info')),
                $this->colourField('Success', _t(__CLASS__ . '.Success', 'Success')),
                $this->colourField('Warning', _t(__CLASS__ . '.Warning', 'Warning')),
                $this->colourField('Error', _t(__CLASS__ . '.Error', 'Error')),
            ])->setHeadingLevel(4),
        ]);

        $fonts = array_combine(
            array_keys(ThemeSettings::config()->get('fonts')),
            array_keys(ThemeSettings::config()->get('fonts')),
        );
        $themeDefault = _t(__CLASS__ . '.ThemeDefault', 'Theme default');

        $fields->addFieldsToTab('Root.Theme.Typography', [
            DropdownField::create('ThemeBodyFont', _t(__CLASS__ . '.BodyFont', 'Body font'), $fonts)
                ->setEmptyString($themeDefault . ' (system)'),
            DropdownField::create('ThemeHeadingFont', _t(__CLASS__ . '.HeadingFont', 'Heading font'), $fonts)
                ->setEmptyString($themeDefault . ' (same as body)'),
            DropdownField::create('ThemeFontScale', _t(__CLASS__ . '.FontScale', 'Text size'), $this->presetOptions('font_scales'))
                ->setDescription(_t(__CLASS__ . '.FontScaleDescription', 'Scales all text and spacing relative to the visitor\'s browser setting.')),
            LiteralField::create('ThemeFontsNote', '<p class="form__field-description">'
                . _t(__CLASS__ . '.FontsNote', 'Fonts are loaded from Google Fonts.')
                . '</p>'),
        ]);

        $fields->addFieldsToTab('Root.Theme.Layout', [
            DropdownField::create('ThemeContainerWidth', _t(__CLASS__ . '.ContainerWidth', 'Site width'), $this->presetOptions('container_widths'))
                ->setDescription(_t(__CLASS__ . '.ContainerWidthDescription', 'The widest that page titles, text and "Site width" blocks get. "Full width" blocks ignore it.')),
            DropdownField::create('ThemeGutter', _t(__CLASS__ . '.Gutter', 'Side padding'), $this->presetOptions('gutters'))
                ->setDescription(_t(__CLASS__ . '.GutterDescription', 'Space between content and the edge of the screen.')),
            DropdownField::create('ThemeSectionSpacing', _t(__CLASS__ . '.SectionSpacing', 'Section spacing'), $this->presetOptions('section_spacings'))
                ->setDescription(_t(__CLASS__ . '.SectionSpacingDescription', 'Space between blocks, and inside sections that have a background. Blocks\' Small to Extra large spacing are fractions of this.')),
            DropdownField::create('ThemeCornerStyle', _t(__CLASS__ . '.CornerStyle', 'Corners'), $this->presetOptions('corner_styles'))
                ->setDescription(_t(__CLASS__ . '.CornerStyleDescription', 'Roundness of cards, buttons and form fields.')),
            DropdownField::create('ThemeBorderWidth', _t(__CLASS__ . '.BorderWidth', 'Borders'), $this->presetOptions('border_widths')),
            CheckboxField::create('ThemeFlat', _t(__CLASS__ . '.Flat', 'Flat buttons and inputs (no shadows or depth)')),
            CheckboxField::create('ThemeNoise', _t(__CLASS__ . '.Noise', 'Subtle grain texture on buttons and inputs')),
        ]);
    }

    public function updateValidate(ValidationResult $result): void
    {
        foreach (array_keys(ThemeSettings::config()->get('colour_tokens')) as $key) {
            $value = trim((string) $this->getOwner()->getField('Theme' . $key));
            if ($value !== '' && !Colour::normalise($value)) {
                $result->addFieldError(
                    'Theme' . $key,
                    _t(__CLASS__ . '.InvalidColour', '"{value}" isn\'t a hex colour, e.g. #005ae1.', ['value' => $value]),
                );
            }
        }
    }

    public function onBeforeWrite(): void
    {
        foreach (array_keys(ThemeSettings::config()->get('colour_tokens')) as $key) {
            $field = 'Theme' . $key;
            $this->getOwner()->setField($field, Colour::normalise($this->getOwner()->getField($field)) ?? '');
        }
    }

    private function colourField(string $key, string $title, ?string $description = null): ThemeColorField
    {
        $field = ThemeColorField::create('Theme' . $key, $title)
            ->setThemeDefault(ThemeSettings::config()->get('theme_defaults')[$key] ?? null);

        if ($description) {
            $field->setDescription($description);
        }

        return $field;
    }

    /**
     * Preset scale from config as dropdown options. The "default" preset
     * stores an empty value so it reads as "theme default" in the database.
     */
    private function presetOptions(string $scale): array
    {
        $options = [];
        foreach (ThemeSettings::config()->get($scale) as $key => $preset) {
            $options[$key === 'default' ? '' : $key] = $preset['label'] ?? $key;
        }

        return $options;
    }
}
