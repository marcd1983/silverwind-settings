<?php

namespace Antlion\SilverwindSettings\Forms;

use Antlion\SilverwindSettings\Colour;
use SilverStripe\Forms\TextField;
use SilverStripe\View\Requirements;

/**
 * Hex colour input paired with the browser's native colour picker.
 *
 * The native picker can't be empty, so the hex text box is the real value:
 * empty means "theme default", which the picker and placeholder then show.
 */
class ThemeColorField extends TextField
{
    protected ?string $themeDefault = null;

    public function __construct($name, $title = null, $value = '', $maxLength = 7, $form = null)
    {
        parent::__construct($name, $title, $value, $maxLength, $form);
        $this->addExtraClass('sw-colorfield__text');
        $this->setAttribute('pattern', '#?([0-9a-fA-F]{3}|[0-9a-fA-F]{6})');
        $this->setAttribute('spellcheck', 'false');
        $this->setAttribute('autocomplete', 'off');
    }

    public function setThemeDefault(?string $hex): static
    {
        $this->themeDefault = Colour::normalise($hex);
        if ($this->themeDefault) {
            $this->setAttribute('placeholder', $this->themeDefault);
        }

        return $this;
    }

    public function getThemeDefault(): ?string
    {
        return $this->themeDefault;
    }

    /**
     * What the native picker shows: the saved colour, else the theme default.
     */
    public function getSwatchValue(): string
    {
        return Colour::normalise($this->getValue()) ?? $this->themeDefault ?? '#000000';
    }

    public function Field($properties = [])
    {
        Requirements::javascript('antlion/silverwind-settings: client/js/theme-color-field.js');
        Requirements::css('antlion/silverwind-settings: client/css/theme-color-field.css');

        return parent::Field($properties);
    }
}
