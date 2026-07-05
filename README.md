# Filament Smart SEO

AI-powered SEO autofill, live Google SERP preview, and optional mobile Open Graph preview for Filament admin panels. SEO metadata is stored in a single polymorphic `seo_metadata` table and can be edited per locale through built-in tab layouts.

---

## Table of contents

1. [Features](#features)
2. [Requirements](#requirements)
3. [Installation](#installation)
4. [Environment variables](#environment-variables)
5. [Configuration](#configuration)
6. [Model setup](#model-setup)
7. [Quick start (single locale)](#quick-start-single-locale)
8. [Multi-language and locale tabs](#multi-language-and-locale-tabs)
9. [AI Autofill behavior](#ai-autofill-behavior)
10. [Real-world examples](#real-world-examples)
11. [Live previews](#live-previews)
12. [Fluent API reference](#fluent-api-reference)
13. [Custom Filament pages (no model)](#custom-filament-pages-no-model)
14. [Database schema](#database-schema)
15. [How it works](#how-it-works)
16. [Publishing and customization](#publishing-and-customization)
17. [Troubleshooting](#troubleshooting)
18. [License](#license)

---

## Features

- **Universal SEO storage** — one `seo_metadata` row per model (`title`, `description`, `keywords`, `og_image`) via a polymorphic relationship.
- **`HasSeo` trait** — adds a `seo()` `morphOne` relationship and removes the SEO row on force delete.
- **`SeoSection::make()`** — drop-in Filament section with title, description, keywords, optional OG image upload, and live previews.
- **Gemini AI autofill** — generates SEO title, meta description, and keyword tags from mapped source fields via the header **AI Autofill** button.
- **Locale tab layouts** — multi-language SEO is managed through per-locale tabs inside `SeoSection` (`EN`, `RU`, and so on).
- **Two multi-language modes** — `translatable()` for Spatie JSON fields on the parent model, or `localeSuffixed()` for `title_en`-style database columns.
- **Custom settings pages** — persist SEO for Filament pages without an Eloquent model via `settingsKey()` and `InteractsWithSeoSettings`.
- **No frontend build** — previews use inline styles and Livewire entanglement; no extra NPM or Tailwind setup is required.

---

## Requirements

- PHP `^8.2`, `^8.3`, or `^8.4`
- Laravel `^12`, or `^13`
- Filament `^4` or `^5`
- [google-gemini-php/client](https://github.com/google-gemini-php/client) `^2.0`
- [spatie/laravel-translatable](https://github.com/spatie/laravel-translatable) `^6.0`
- A valid [Google Gemini API key](https://aistudio.google.com/apikey)

---

## Installation

```bash
composer require martin6363/filament-smart-seo
```

Publish config, migrations, and optionally translations or views:

```bash
php artisan vendor:publish --tag=filament-smart-seo-config
php artisan vendor:publish --tag=filament-smart-seo-migrations
php artisan migrate
```

Register the plugin on your Filament panel:

```php
use Filament\Panel;
use Martin6363\FilamentSmartSeo\FilamentSmartSeoPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentSmartSeoPlugin::make(),
        ]);
}
```

Add the `HasSeo` trait to any Eloquent model that should own SEO metadata (see [Model setup](#model-setup)).

---

## Environment variables

```dotenv
GEMINI_API_KEY=your-google-ai-studio-api-key
FILAMENT_SMART_SEO_GEMINI_MODEL=gemini-2.5-flash
FILAMENT_SMART_SEO_OG_DISK=public
```

| Variable | Purpose |
|----------|---------|
| `GEMINI_API_KEY` | Google AI Studio API key. Required for AI autofill. |
| `FILAMENT_SMART_SEO_GEMINI_MODEL` | Primary Gemini model name. Fallback models are tried automatically on quota errors. |
| `FILAMENT_SMART_SEO_OG_DISK` | Filesystem disk used for OG image uploads. Default: `public`. |

---

## Configuration

Published file: `config/filament-smart-seo.php`

```php
return [
    'available_locales' => ['en', 'ru'],
    'api_key' => env('GEMINI_API_KEY'),
    'gemini_model' => 'gemini-2.5-flash',
    'max_title_length' => 60,
    'max_description_length' => 160,
    'preview_base_url' => env('APP_URL'),
    'preview_fallback_title' => 'Page title',
    'preview_fallback_description' => 'Your meta description will appear here...',
    'og_image_disk' => 'public',
    'og_image_directory' => 'seo/og-images',
    'settings_store' => Martin6363\FilamentSmartSeo\Stores\DatabaseSeoSettingsStore::class,
];
```

| Key | Purpose |
|-----|---------|
| `available_locales` | Default locales for `translatable()` and `localeSuffixed()` tab layouts. |
| `max_title_length` / `max_description_length` | Length limits used by Gemini and the SERP counter badges. |
| `preview_base_url` | Default URL shown in the Google preview breadcrumb when `previewUrl()` is not set. |
| `preview_fallback_title` / `preview_fallback_description` | Placeholder text in previews when SEO fields are empty. |
| `og_image_disk` / `og_image_directory` | Storage location for uploaded Open Graph images. |
| `settings_store` | Class implementing `SeoSettingsStore` for custom Filament pages. |

---

## Model setup

```php
use Illuminate\Database\Eloquent\Model;
use Martin6363\FilamentSmartSeo\Traits\HasSeo;

class Vehicle extends Model
{
    use HasSeo;
}
```

This registers a `seo()` `morphOne` relationship to `SeoMetadata`. Translatable SEO attributes (`title`, `description`, `keywords`) are stored as JSON through Spatie Translatable on the `SeoMetadata` model itself, regardless of how the parent model stores its own content.

---

## Quick start (single locale)

Use this when your resource has one language and no locale-specific source columns.

```php
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Martin6363\FilamentSmartSeo\Forms\Components\SeoSection;

TextInput::make('title')->required(),
RichEditor::make('content')->required(),

SeoSection::make()
    ->sourceTitleField('title')
    ->sourceDescriptionField('content');
```

Click **AI Autofill** in the section header to generate SEO from the mapped source fields. Generation is always triggered manually through this button. The Google SERP preview updates live as you type or edit the SEO fields.

---

## Multi-language and locale tabs

When your application has more than one language, `SeoSection` renders **locale tabs** (`EN`, `RU`, and so on). Each tab contains its own `title`, `description`, `keywords`, and SERP preview for that locale.

This is the intended multi-language workflow. Map your source fields once with base names (`title`, `description`, `content`). The package resolves the correct source per locale based on the mode you choose.

### Choosing a mode

| Mode | Parent model source storage | Source field mapping | SEO storage |
|------|----------------------------|----------------------|-------------|
| `translatable()` | Spatie JSON on the parent (`title`, `content` as translation maps) | Base names: `title`, `content` | Per-locale JSON in `seo_metadata` |
| `localeSuffixed()` | Separate columns per locale (`title_en`, `description_ru`, ...) | Base names: `title`, `description` (resolved to `title_en`, `title_ru`, ...) | Per-locale JSON in `seo_metadata` |

Locales default to `config('filament-smart-seo.available_locales')`. Override per section:

```php
->locales(['en', 'ru'])
```

### Important: parent tabs vs SEO tabs

Your parent form may have its own translation tabs (for example, a `Translations` tab group with English and Russian content fields). That is separate from the SEO locale tabs inside `SeoSection`.

- **Parent tabs** hold the page content (`title_en`, `description_en`, ...).
- **SEO tabs** hold generated metadata (`seo.title.en`, `seo.description.en`, ...) and the preview for that locale.

AI autofill always reads source content for a specific locale and writes SEO into the matching SEO tab fields.

---

## AI Autofill behavior

AI generation is always locale-aware. The scope of a single **AI Autofill** click depends on the mode:

| Mode | What happens when you click AI Autofill |
|------|----------------------------------------|
| Single locale (no tab mode) | Generates SEO for the default application locale. |
| `translatable()` | Generates SEO **only for the currently active SEO tab**. Switch tabs and click again for other locales. |
| `localeSuffixed()` | Generates SEO **for every configured locale** in one request cycle. Each locale uses its own suffixed source columns (`title_en`, `description_en`, ...). Locales without source content are skipped. |

### Recommended workflow with `translatable()`

1. Open the SEO section and select the **EN** tab.
2. Fill English `title` and `content` on the parent form.
3. Click **AI Autofill**. Only the **EN** SEO fields are filled.
4. Switch to the **RU** SEO tab, fill Russian parent content, click **AI Autofill** again.
5. Save the form. Each locale is persisted in `seo_metadata`.

Source text for Spatie translatable parents is read via `getTranslation()`, so generation works even when the parent form only displays one locale at a time.

### Recommended workflow with `localeSuffixed()`

1. Fill all locale-specific source columns on the parent form (`title_en`, `description_en`, `title_ru`, `description_ru`, ...).
2. Open any SEO tab and click **AI Autofill** once.
3. SEO is generated for every locale that has source content.
4. Switch SEO tabs to review or fine-tune each locale. Each tab has its own live SERP preview.

### AI Autofill

SEO generation is triggered **only** through the **AI Autofill** header button in `SeoSection`. There is no automatic generation on source field blur or change. This keeps API usage predictable and gives editors full control over when Gemini is called.

### What Gemini generates

For each target locale, the service returns:

- SEO title (max length from config, default 60 characters)
- Meta description (max length from config, default 160 characters)
- 5 to 10 keyword phrases derived from the source context

Keywords are always generated from the mapped title and description context for that locale. They are not typed manually unless you edit them after generation.

---

## Real-world examples

### Vehicle resource (`localeSuffixed`)

A typical setup when each locale has its own database columns. Parent content lives in translation tabs; SEO lives in `SeoSection` locale tabs.

```php
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Martin6363\FilamentSmartSeo\Forms\Components\SeoSection;

private const LOCALES = ['en', 'ru'];

Tabs::make('Translations')
    ->tabs([
        Tab::make('English')->schema([
            TextInput::make('title_en')->required(),
            RichEditor::make('description_en')->columnSpanFull(),
        ]),
        Tab::make('Russian')->schema([
            TextInput::make('title_ru'),
            RichEditor::make('description_ru')->columnSpanFull(),
        ]),
    ])
    ->columnSpanFull(),

SeoSection::make()
    ->localeSuffixed()
    ->locales(self::LOCALES)
    ->sourceTitleField('title')
    ->sourceDescriptionField('description')
    ->previewUrl(url('/vehicles'))
    ->withoutImage();
```

**How it works:**

1. Content editors fill `title_en` / `description_en` and `title_ru` / `description_ru` in the parent translation tabs.
2. One **AI Autofill** click generates SEO for both `en` and `ru` (when source text exists).
3. Open the **EN** or **RU** tab inside `SeoSection` to review that locale's title, description, keywords, and SERP preview.
4. `previewUrl()` controls the domain and path shown in the Google preview breadcrumb.

### Article resource (`translatable`)

Use when the parent model stores translations as Spatie JSON (`title`, `content`).

```php
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Martin6363\FilamentSmartSeo\Forms\Components\SeoSection;

TextInput::make('title')->required(),
RichEditor::make('content')->required(),

SeoSection::make()
    ->translatable()
    ->sourceTitleField('title')
    ->sourceDescriptionField('content')
    ->withMobileOgPreview();
```

**How it works:**

1. Fill the parent `title` and `content` for the language you are editing.
2. Open the matching SEO tab (`EN` or `RU`).
3. Click **AI Autofill**. Only the **active SEO tab** is filled.
4. Repeat for other locales before saving.

---

## Live previews

### Google SERP preview

Included by default in every `SeoSection`. Shows:

- Favicon placeholder and URL breadcrumb
- Live title and meta description
- Character counter badges with color hints for recommended length ranges

The preview binds to the active locale tab through Livewire entanglement and updates as you type.

### Mobile and social preview (optional)

```php
->withMobileOgPreview()
```

Adds a Facebook-style Open Graph card below the Google preview. It shows:

- OG image (when the upload field is enabled and a file is selected)
- Site domain
- Title and description with two-line clamping

Does not replace or modify the Google preview. Enable only where social sharing preview is useful.

```php
->withMobileOgPreview(fn (): bool => auth()->user()?->isAdmin())
```

Pass a closure for conditional display.

---

## Fluent API reference

| Method | Description |
|--------|-------------|
| `sourceTitleField('title')` | Form field used as the SEO title source. Base name; suffixed per locale in `localeSuffixed()` mode. |
| `sourceDescriptionField('content')` | Form field used as the SEO description or body source. |
| `translatable()` | Enable per-locale SEO tabs for Spatie JSON parent fields. AI autofill targets the active SEO tab only. |
| `localeSuffixed()` | Enable per-locale SEO tabs when parent source fields use `field_locale` columns. AI autofill runs for all configured locales. |
| `locales(['en', 'ru'])` | Override `available_locales` for this section. |
| `withoutImage()` | Hide the OG image upload field. |
| `withImage()` | Show the OG image upload field. Default when not calling `withoutImage()`. |
| `previewUrl(url('/vehicles'))` | Base URL for the Google preview breadcrumb trail. |
| `withMobileOgPreview()` | Show mobile Open Graph preview below the Google SERP preview. |
| `fullWidth()` | Span the full form width. Enabled by default. |
| `settingsKey('site.seo')` | Persist via `SeoSettingsStore` instead of the `seo` relationship. |

---

## Custom Filament pages (no model)

For settings pages without an Eloquent record, use `settingsKey()` with the `InteractsWithSeoSettings` trait:

```php
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Martin6363\FilamentSmartSeo\Concerns\InteractsWithSeoSettings;
use Martin6363\FilamentSmartSeo\Forms\Components\SeoSection;

class SiteSettings extends Page
{
    use InteractsWithSeoSettings;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill($this->getSeoSettingsFormData());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                SeoSection::make()
                    ->settingsKey('site.seo')
                    ->translatable()
                    ->sourceTitleField('site_name')
                    ->sourceDescriptionField('site_tagline'),
            ]);
    }

    public function save(): void
    {
        $this->saveSeoSettingsFromFormData($this->form->getState());
    }
}
```

Data is stored in `seo_metadata` with a unique `settings_key` column via `DatabaseSeoSettingsStore`. Swap `config('filament-smart-seo.settings_store')` for any class implementing `SeoSettingsStore`.

---

## Database schema

Table: `seo_metadata`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint | Primary key |
| `seoble_type` / `seoble_id` | morph, nullable | Polymorphic owner when using `HasSeo` |
| `settings_key` | string, nullable, unique | Identifier for custom Filament pages |
| `title` | json | Spatie translatable. Locale map, for example `{"en":"...","ru":"..."}` |
| `description` | json | Spatie translatable |
| `keywords` | json | Spatie translatable. Array of tags per locale |
| `og_image` | string, nullable | Shared across all locales |
| `created_at` / `updated_at` | timestamps | |

Either `seoble_type` + `seoble_id` or `settings_key` identifies a row. A model using `HasSeo` always uses the morph columns.

---

## How it works

```
Parent form fields (title, content, title_en, title_ru, ...)
        |
        v
  AI Autofill button  --->  GeminiSeoService (per locale)
        |                          |
        |                          v
        |                   title, description, keywords[]
        v
  seo_metadata (morphOne)   or   SeoSettingsStore (settings_key)
        |
        v
  Locale tabs: fields + Google SERP preview (+ optional mobile OG preview)
```

1. `SeoSection` binds to the `seo` relationship on the record, or to a settings key on custom pages.
2. In multi-language mode, each locale tab holds `title.{locale}`, `description.{locale}`, and `keywords.{locale}`.
3. **AI Autofill** (button only) sends locale-specific source text to Gemini and writes results into the correct tab fields.
4. Live previews entangle to the active tab's form state and update in real time.
5. On save, tabbed locale data is collapsed into Spatie-compatible JSON on `SeoMetadata`.

---

## Publishing and customization

| Tag | Contents |
|-----|----------|
| `filament-smart-seo-config` | `config/filament-smart-seo.php` |
| `filament-smart-seo-migrations` | `create_seo_metadata_table` migration |
| `filament-smart-seo-translations` | Language files under `lang/` |
| `filament-smart-seo-views` | Blade views including `seo-preview.blade.php` |

To replace the settings persistence layer, implement `Martin6363\FilamentSmartSeo\Contracts\SeoSettingsStore` and update `settings_store` in config.

---

## Troubleshooting

**AI Autofill does nothing or shows an empty-source error**

- Confirm `GEMINI_API_KEY` is set in `.env`.
- Ensure the mapped source fields contain text for the target locale.
- In `translatable()` mode, check that the correct SEO tab is active before clicking the button.
- In `localeSuffixed()` mode, confirm suffixed columns exist on the form (`title_en`, not only `title`).

**SEO for one locale is missing after save**

- In `translatable()` mode, generate and save each locale separately before leaving the page.
- Verify `available_locales` or `->locales()` matches your application's languages.

**Preview does not update**

- SEO previews only appear inside locale tabs when using `translatable()` or `localeSuffixed()`. Switch to the tab for the locale you are editing.
- Fields use `live(debounce: 300)`. Wait briefly after typing.

**Gemini quota or demand errors**

- The service automatically tries fallback models (`gemini-2.5-flash`, `gemini-2.0-flash`, `gemini-1.5-flash`). Retry after a short delay.

**OG image does not appear in mobile preview**

- Call `withMobileOgPreview()` on the section.
- Do not call `withoutImage()` if you need an upload field.
- Confirm the file is stored on the disk defined in `og_image_disk`.

---

## License

The MIT License (MIT). See [LICENSE](LICENSE) for details.
