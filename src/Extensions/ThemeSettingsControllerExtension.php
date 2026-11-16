<?php

namespace Antlion\SilverwindSettings\Extensions;

use Antlion\SilverwindSettings\ThemeSettings;
use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\Core\Extension;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\View\Requirements;

/**
 * Adds the Theme settings' font stylesheet and custom-property block to
 * every front-end page's <head>, so themes don't need any template changes.
 *
 * @extends Extension<ContentController>
 */
class ThemeSettingsControllerExtension extends Extension
{
    public function onAfterInit(): void
    {
        $tags = ThemeSettings::create(SiteConfig::current_site_config())->getHeadTags();

        if ($tags !== '') {
            Requirements::insertHeadTags($tags, 'silverwind-settings');
        }
    }
}
