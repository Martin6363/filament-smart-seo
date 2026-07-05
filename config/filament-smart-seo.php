<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Available locales
    |--------------------------------------------------------------------------
    |
    | Locales used for locale-suffixed layouts and multi-language SEO fields.
    | Align these with your Spatie Translatable / panel locale configuration.
    |
    */

    'available_locales' => [
        'en',
        'ru',
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Gemini
    |--------------------------------------------------------------------------
    */

    'api_key' => env('GEMINI_API_KEY'),

    'gemini_model' => env('FILAMENT_SMART_SEO_GEMINI_MODEL', env('GEMINI_MODEL', 'gemini-2.5-flash')),

    /*
    |--------------------------------------------------------------------------
    | AI Autofill button
    |--------------------------------------------------------------------------
    |
    | When false, the header "AI Autofill" action is hidden globally. Use
    | SeoSection::withoutAiAutofill() to disable it for a single section.
    |
    */

    'ai_autofill_enabled' => (bool) env('FILAMENT_SMART_SEO_AI_AUTOFILL_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Classic SEO length limits
    |--------------------------------------------------------------------------
    |
    | Used for live counter badges in the Google SERP preview.
    |
    */

    'max_title_length' => 60,

    'max_description_length' => 160,

    /*
    |--------------------------------------------------------------------------
    | Preview defaults
    |--------------------------------------------------------------------------
    */

    'preview_base_url' => env('APP_URL', 'https://example.com'),

    'preview_fallback_title' => 'Page title',

    'preview_fallback_description' => 'Your meta description will appear here. Write a compelling summary that encourages clicks from search results.',

    /*
    |--------------------------------------------------------------------------
    | Open Graph image upload
    |--------------------------------------------------------------------------
    */

    'og_image_disk' => env('FILAMENT_SMART_SEO_OG_DISK', 'public'),

    'og_image_directory' => 'seo/og-images',

    /*
    |--------------------------------------------------------------------------
    | Settings store (custom Filament pages without an Eloquent model)
    |--------------------------------------------------------------------------
    |
    | Used by SeoSection::settingsKey() and the InteractsWithSeoSettings trait.
    | Swap for your own implementation of SeoSettingsStore if needed.
    |
    */

    'settings_store' => Martin6363\FilamentSmartSeo\Stores\DatabaseSeoSettingsStore::class,

];
