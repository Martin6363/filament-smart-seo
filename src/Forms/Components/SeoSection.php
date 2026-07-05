<?php

declare(strict_types=1);

namespace Martin6363\FilamentSmartSeo\Forms\Components;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Martin6363\FilamentSmartSeo\Models\SeoMetadata;
use Martin6363\FilamentSmartSeo\Services\GeminiSeoService;
use Spatie\Translatable\HasTranslations;
use Throwable;

class SeoSection extends Section
{
    protected string | Closure | null $sourceTitleField = null;

    protected string | Closure | null $sourceDescriptionField = null;

    protected bool | Closure $hasImage = true;

    protected bool | Closure $localeSuffixed = false;

    protected bool | Closure $spatieTranslatable = false;

    /**
     * @var array<int, string>|Closure|null
     */
    protected array | Closure | null $locales = null;

    /**
     * @var array<string, array<string, mixed>>|null
     */
    protected ?array $pendingSpatieTranslations = null;

    protected string | Closure | null $settingsKey = null;

    protected string | Closure | null $previewUrl = null;

    protected bool | Closure $mobileOgPreview = false;

    protected bool | Closure | null $aiAutofill = null;

    /**
     * @param  string | array<Component | Action> | Htmlable | Closure | null  $heading
     */
    public static function make(string | array | Htmlable | Closure | null $heading = null): static
    {
        $heading ??= fn (): string => __('filament-smart-seo::section.heading');

        return parent::make($heading);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->icon(Heroicon::OutlinedMagnifyingGlass);
        $this->columns(1);
        $this->collapsible();
        $this->fullWidth();

        $this->relationship(
            name: 'seo',
            condition: fn (SeoSection $component): bool => blank($component->getSettingsKey()),
        );

        $this->mutateRelationshipDataBeforeFillUsing(function (array $data, SeoSection $component): array {
            return $component->expandSpatieTabbedRelationshipData($data);
        });

        $this->mutateRelationshipDataBeforeSaveUsing(function (array $data, SeoSection $component): array {
            return $component->persistSpatieTabbedRelationshipData($data);
        });

        $this->mutateRelationshipDataBeforeCreateUsing(function (array $data, SeoSection $component): array {
            return $component->persistSpatieTabbedRelationshipData($data);
        });

        $this->saveRelationshipsUsing(function (SeoSection $component): void {
            $component->applyPendingSpatieTranslations();
        });

        $this->schema(fn (SeoSection $component): array => $component->buildSeoSchema());

        $this->headerActions([
            Action::make('generateSeo')
                ->label(__('filament-smart-seo::actions.generate.label'))
                ->icon(Heroicon::OutlinedSparkles)
                ->color('primary')
                ->tooltip(__('filament-smart-seo::actions.generate.tooltip'))
                ->visible(fn (SeoSection $component): bool => $component->hasAiAutofill())
                ->action(function (SeoSection $component, Get $get, Set $set): void {
                    $component->runGenerationWithButton($get, $set);
                }),
        ]);
    }

    public function sourceTitleField(string | Closure | null $field): static
    {
        $this->sourceTitleField = $field;

        return $this;
    }

    public function sourceDescriptionField(string | Closure | null $field): static
    {
        $this->sourceDescriptionField = $field;

        return $this;
    }

    public function withoutImage(bool | Closure $condition = true): static
    {
        $this->hasImage = fn (SeoSection $component): bool => ! $component->evaluate($condition);

        return $this;
    }

    public function withImage(bool | Closure $condition = true): static
    {
        $this->hasImage = $condition;

        return $this;
    }

    /**
     * Per-locale SEO tabs for column-suffixed source fields (`title_en`, …).
     * AI Autofill generates SEO for every configured locale.
     */
    public function localeSuffixed(bool | Closure $condition = true): static
    {
        $this->localeSuffixed = $condition;

        return $this;
    }

    /**
     * Per-locale SEO tabs for Spatie Translatable models (locales from config by default).
     * AI Autofill targets the currently open tab only.
     */
    public function translatable(bool | Closure $condition = true): static
    {
        $this->spatieTranslatable = $condition;
        $this->localeSuffixed = $condition;

        return $this;
    }

    public function isSpatieTranslatable(): bool
    {
        return (bool) $this->evaluate($this->spatieTranslatable);
    }

    public function usesLocaleTabs(): bool
    {
        return $this->isLocaleSuffixed();
    }

    /**
     * @param  array<int, string>|Closure|null  $locales
     */
    public function locales(array | Closure | null $locales): static
    {
        $this->locales = $locales;

        return $this;
    }

    /**
     * Persist SEO state through the settings store instead of a model relationship.
     */
    public function settingsKey(string | Closure | null $key): static
    {
        $this->settingsKey = $key;

        return $this;
    }

    public function previewUrl(string | Closure | null $url): static
    {
        $this->previewUrl = $url;

        return $this;
    }

    /**
     * Show a mobile Open Graph / social share card preview below the Google SERP preview.
     */
    public function withMobileOgPreview(bool | Closure $condition = true): static
    {
        $this->mobileOgPreview = $condition;

        return $this;
    }

    public function hasMobileOgPreview(): bool
    {
        return (bool) $this->evaluate($this->mobileOgPreview);
    }

    /**
     * Show the header AI Autofill action. Defaults to config `ai_autofill_enabled`.
     */
    public function withAiAutofill(bool | Closure $condition = true): static
    {
        $this->aiAutofill = $condition;

        return $this;
    }

    /**
     * Hide the header AI Autofill action for manual-only SEO editing.
     */
    public function withoutAiAutofill(bool | Closure $condition = true): static
    {
        $this->aiAutofill = fn (SeoSection $component): bool => ! $component->evaluate($condition);

        return $this;
    }

    public function hasAiAutofill(): bool
    {
        if ($this->aiAutofill !== null) {
            return (bool) $this->evaluate($this->aiAutofill);
        }

        return (bool) config('filament-smart-seo.ai_autofill_enabled', true);
    }

    /**
     * Span the SEO section across the full width of the parent grid.
     */
    public function fullWidth(bool | Closure $condition = true): static
    {
        if ($this->evaluate($condition)) {
            $this->columnSpanFull();

            return $this;
        }

        $this->columnSpan(['default' => 1]);

        return $this;
    }

    public function getSourceTitleField(): ?string
    {
        $field = $this->evaluate($this->sourceTitleField);

        return filled($field) ? (string) $field : null;
    }

    public function getSourceDescriptionField(): ?string
    {
        $field = $this->evaluate($this->sourceDescriptionField);

        return filled($field) ? (string) $field : null;
    }

    public function hasImage(): bool
    {
        return (bool) $this->evaluate($this->hasImage);
    }

    public function isLocaleSuffixed(): bool
    {
        return (bool) $this->evaluate($this->localeSuffixed);
    }

    /**
     * @return list<string>
     */
    public function getLocales(): array
    {
        /** @var array<int, string> $locales */
        $locales = $this->evaluate($this->locales) ?? config('filament-smart-seo.available_locales', ['en', 'hy', 'zh']);

        return array_values(array_filter($locales));
    }

    public function getSettingsKey(): ?string
    {
        $key = $this->evaluate($this->settingsKey);

        return filled($key) ? (string) $key : null;
    }

    public function getPreviewUrl(): string
    {
        $url = $this->evaluate($this->previewUrl);

        if (filled($url)) {
            return (string) $url;
        }

        return (string) config('filament-smart-seo.preview_base_url', url('/'));
    }

    public function getMaxTitleLength(): int
    {
        return (int) config('filament-smart-seo.max_title_length', 60);
    }

    public function getMaxDescriptionLength(): int
    {
        return (int) config('filament-smart-seo.max_description_length', 160);
    }

    /**
     * @return array<int, Component>
     */
    protected function buildSeoSchema(): array
    {
        $fields = $this->usesLocaleTabs()
            ? [$this->buildLocaleTabbedFields()]
            : $this->buildSingleLocaleFields();

        if (! $this->usesLocaleTabs()) {
            $fields[] = $this->buildPreviewField();
        }

        if ($this->hasImage()) {
            $fields[] = FileUpload::make('og_image')
                ->label(__('filament-smart-seo::fields.og_image'))
                ->image()
                ->disk((string) config('filament-smart-seo.og_image_disk', 'public'))
                ->directory((string) config('filament-smart-seo.og_image_directory', 'seo/og-images'))
                ->visibility('public')
                ->helperText(__('filament-smart-seo::fields.og_image_help'));
        }

        return $fields;
    }

    /**
     * @return array<int, Component>
     */
    protected function buildSingleLocaleFields(): array
    {
        return [
            TextInput::make('title')
                ->label(__('filament-smart-seo::fields.title'))
                ->maxLength($this->getMaxTitleLength() + 20)
                ->live(debounce: 300)
                ->helperText(__('filament-smart-seo::fields.title_help', ['max' => $this->getMaxTitleLength()])),

            Textarea::make('description')
                ->label(__('filament-smart-seo::fields.description'))
                ->rows(3)
                ->maxLength($this->getMaxDescriptionLength() + 40)
                ->live(debounce: 300)
                ->helperText(__('filament-smart-seo::fields.description_help', ['max' => $this->getMaxDescriptionLength()])),

            TagsInput::make('keywords')
                ->label(__('filament-smart-seo::fields.keywords'))
                ->placeholder(__('filament-smart-seo::fields.keywords_placeholder'))
                ->helperText(__('filament-smart-seo::fields.keywords_help'))
                ->live(debounce: 300),
        ];
    }

    protected function buildLocaleTabbedFields(): Tabs
    {
        $tabs = [];

        foreach ($this->getLocales() as $locale) {
            $tabs[] = Tab::make(Str::upper($locale))
                ->schema([
                    TextInput::make("title.{$locale}")
                        ->label(__('filament-smart-seo::fields.title'))
                        ->maxLength($this->getMaxTitleLength() + 20)
                        ->live(debounce: 300),

                    Textarea::make("description.{$locale}")
                        ->label(__('filament-smart-seo::fields.description'))
                        ->rows(3)
                        ->maxLength($this->getMaxDescriptionLength() + 40)
                        ->live(debounce: 300),

                    TagsInput::make("keywords.{$locale}")
                        ->label(__('filament-smart-seo::fields.keywords'))
                        ->placeholder(__('filament-smart-seo::fields.keywords_placeholder'))
                        ->live(debounce: 300),

                    $this->buildPreviewField($locale),
                ]);
        }

        return Tabs::make('seoLocales')
            ->tabs($tabs)
            ->live()
            ->columnSpanFull();
    }

    protected function buildPreviewField(?string $previewLocale = null): ViewField
    {
        $fieldName = filled($previewLocale) ? "seo_preview_{$previewLocale}" : 'seo_preview';

        return ViewField::make($fieldName)
            ->hiddenLabel()
            ->view('filament-smart-seo::forms.components.seo-preview')
            ->dehydrated(false)
            ->live()
            ->columnSpanFull()
            ->viewData(function (Get $get, ViewField $component) use ($previewLocale): array {
                /** @var SeoSection|null $section */
                $section = $component->getContainer()->getParentComponent();

                while ($section !== null && ! $section instanceof SeoSection) {
                    $section = $section->getContainer()->getParentComponent();
                }

                $section ??= $this;
                $locale = $previewLocale ?? $section->resolveActiveSeoTabLocale();

                if ($section->usesLocaleTabs() && filled($previewLocale)) {
                    $titleStatePath = $component->resolveRelativeStatePath("title.{$previewLocale}");
                    $descriptionStatePath = $component->resolveRelativeStatePath("description.{$previewLocale}");
                } else {
                    $titleStatePath = $component->resolveRelativeStatePath('title');
                    $descriptionStatePath = $component->resolveRelativeStatePath('description');
                }

                $previewUrl = $section->getPreviewUrl();
                $parsedUrl = parse_url($previewUrl) ?: [];
                $host = $parsedUrl['host'] ?? parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';
                $ogImageDisk = (string) config('filament-smart-seo.og_image_disk', 'public');
                $ogImageBaseUrl = rtrim(Storage::disk($ogImageDisk)->url('/'), '/');

                return [
                    'title' => $section->resolveLocalizedValue($get, 'title', $locale)
                        ?: (string) config('filament-smart-seo.preview_fallback_title'),
                    'description' => $section->resolveLocalizedValue($get, 'description', $locale)
                        ?: (string) config('filament-smart-seo.preview_fallback_description'),
                    'url' => $previewUrl,
                    'maxTitleLength' => $section->getMaxTitleLength(),
                    'maxDescriptionLength' => $section->getMaxDescriptionLength(),
                    'isFallbackTitle' => blank($section->resolveLocalizedValue($get, 'title', $locale)),
                    'isFallbackDescription' => blank($section->resolveLocalizedValue($get, 'description', $locale)),
                    'titleStatePath' => $titleStatePath,
                    'descriptionStatePath' => $descriptionStatePath,
                    'locale' => $locale,
                    'previewLocale' => $previewLocale,
                    'isLocaleSuffixed' => $section->usesLocaleTabs(),
                    'usesTranslatableMap' => false,
                    'syncActiveLocaleFromLivewire' => false,
                    'showMobileOgPreview' => $section->hasMobileOgPreview(),
                    'ogImageStatePath' => $section->hasImage()
                        ? $component->resolveRelativeStatePath('og_image')
                        : null,
                    'ogImageBaseUrl' => $ogImageBaseUrl,
                    'siteName' => strtoupper(preg_replace('/^www\./i', '', $host)),
                ];
            });
    }

    protected function findRootField(string $fieldName): ?Component
    {
        $statePath = $this->resolveRelativeStatePath($fieldName);

        $component = $this->getRootContainer()->getComponentByStatePath(
            $statePath,
            withHidden: true,
            withAbsoluteStatePath: true,
        );

        return $component instanceof Component ? $component : null;
    }

    public function runGeneration(
        Get $get,
        Set $set,
        bool $notify = true,
        ?string $locale = null,
    ): void {
        $locale ??= $this->resolveActiveLocale();

        $sourceTitle = $this->readSourceValue(
            $get,
            $this->getSourceTitleField(),
            $locale,
        );
        $sourceDescription = $this->readSourceValue(
            $get,
            $this->getSourceDescriptionField(),
            $locale,
        );

        if (blank($sourceTitle) && blank($sourceDescription)) {
            if ($notify) {
                Notification::make()
                    ->title(__('filament-smart-seo::notifications.empty_source.title'))
                    ->body(__('filament-smart-seo::notifications.empty_source.body'))
                    ->warning()
                    ->send();
            }

            return;
        }

        $notificationId = 'filament-smart-seo-'.md5($this->getStatePath() ?? 'seo');

        if ($notify) {
            Notification::make($notificationId)
                ->title(__('filament-smart-seo::notifications.generating.title'))
                ->body(__('filament-smart-seo::notifications.generating.body'))
                ->info()
                ->persistent()
                ->send();
        }

        try {
            $result = app(GeminiSeoService::class)->generate(
                sourceTitle: $sourceTitle,
                sourceDescription: $sourceDescription,
                locale: $locale,
            );

            $this->applyGeneratedState($set, $get, $result, $locale);

            if ($notify) {
                Notification::make($notificationId)
                    ->title(__('filament-smart-seo::notifications.success.title'))
                    ->body(__('filament-smart-seo::notifications.success.body'))
                    ->success()
                    ->send();
            }
        } catch (Throwable $exception) {
            if ($notify) {
                Notification::make($notificationId)
                    ->title(__('filament-smart-seo::notifications.failed.title'))
                    ->body($exception->getMessage())
                    ->danger()
                    ->send();
            }

            throw $exception;
        }
    }

    public function runGenerationWithButton(Get $get, Set $set): void
    {
        $locales = match (true) {
            $this->isSpatieTranslatable() => [$this->resolveActiveSeoTabLocale()],
            $this->usesLocaleTabs() => $this->getLocales(),
            default => [app()->getLocale()],
        };

        $notificationId = 'filament-smart-seo-'.md5($this->getStatePath() ?? 'seo');

        Notification::make($notificationId)
            ->title(__('filament-smart-seo::notifications.generating.title'))
            ->body(__('filament-smart-seo::notifications.generating.body'))
            ->info()
            ->persistent()
            ->send();

        $generatedLocales = 0;
        $lastException = null;

        foreach ($locales as $locale) {
            try {
                $this->runGeneration($get, $set, notify: false, locale: $locale);
                $generatedLocales++;
            } catch (Throwable $exception) {
                $lastException = $exception;
            }
        }

        if ($generatedLocales === 0) {
            Notification::make($notificationId)
                ->title(__('filament-smart-seo::notifications.failed.title'))
                ->body($lastException?->getMessage() ?? __('filament-smart-seo::notifications.empty_source.body'))
                ->danger()
                ->send();

            return;
        }

        Notification::make($notificationId)
            ->title(__('filament-smart-seo::notifications.success.title'))
            ->body(__('filament-smart-seo::notifications.success.body'))
            ->success()
            ->send();
    }

    protected function readSourceValue(
        Get $get,
        ?string $fieldName,
        ?string $locale = null,
    ): string {
        if (blank($fieldName)) {
            return '';
        }

        $resolvedFieldName = $locale !== null
            ? $this->resolveSourceFieldNameForLocale($fieldName, $locale)
            : $fieldName;

        if (
            $locale !== null
            && $this->isSpatieTranslatable()
            && $resolvedFieldName === $fieldName
        ) {
            return $this->readSpatieSourceValue($get, $fieldName, $locale);
        }

        $value = $get($resolvedFieldName);

        return $this->stringifySourceValue($value, $locale);
    }

    protected function stringifySourceValue(mixed $value, ?string $locale = null): string
    {
        if (is_array($value)) {
            $locale ??= $this->resolveActiveLocale();
            $value = $value[$locale] ?? Arr::first(array_filter($value, is_string(...))) ?? '';
        }

        if (! is_string($value)) {
            return '';
        }

        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    /**
     * @param  array{title: string, description: string, keywords: list<string>}  $result
     */
    protected function applyGeneratedState(Set $set, Get $get, array $result, string $locale): void
    {
        if ($this->usesLocaleTabs()) {
            $this->setSeoField($set, "title.{$locale}", $result['title']);
            $this->setSeoField($set, "description.{$locale}", $result['description']);
            $this->setSeoField($set, "keywords.{$locale}", $result['keywords']);

            return;
        }

        $this->setSeoField($set, 'title', $result['title']);
        $this->setSeoField($set, 'description', $result['description']);
        $this->setSeoField($set, 'keywords', $result['keywords']);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function collapseLocaleTabbedData(array $data): array
    {
        $collapsed = [];

        foreach (SeoMetadata::make()->getTranslatableAttributes() as $field) {
            $localeMap = [];

            foreach ($this->getLocales() as $locale) {
                $dottedKey = "{$field}.{$locale}";

                if (array_key_exists($dottedKey, $data)) {
                    $localeMap[$locale] = $data[$dottedKey];
                }
            }

            if ($localeMap !== []) {
                $collapsed[$field] = $localeMap;

                continue;
            }

            if (
                array_key_exists($field, $data)
                && is_array($data[$field])
                && $this->isLocaleMap($data[$field])
            ) {
                $collapsed[$field] = $data[$field];
            }
        }

        return $collapsed;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function expandSpatieTabbedRelationshipData(array $data): array
    {
        if (! $this->isSpatieTranslatable()) {
            return $data;
        }

        $record = $this->getCachedExistingRecord();

        if (! $record instanceof SeoMetadata) {
            return $data;
        }

        foreach ($record->getTranslatableAttributes() as $field) {
            unset($data[$field]);

            foreach ($this->getLocales() as $locale) {
                $data["{$field}.{$locale}"] = $record->getTranslation(
                    $field,
                    $locale,
                    useFallbackLocale: false,
                );
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function persistSpatieTabbedRelationshipData(array $data): array
    {
        if (! $this->isSpatieTranslatable()) {
            return $data;
        }

        $translations = $this->collapseLocaleTabbedData($data);

        if ($translations === []) {
            return $this->stripLocaleTabbedFields($data);
        }

        $record = $this->getCachedExistingRecord();

        if ($record instanceof SeoMetadata) {
            foreach ($translations as $field => $localeMap) {
                $record->setTranslations($field, $localeMap);
            }

            $record->save();
            $this->cachedExistingRecord($record);
        } else {
            $this->pendingSpatieTranslations = $translations;
        }

        return $this->stripLocaleTabbedFields($data);
    }

    public function applyPendingSpatieTranslations(): void
    {
        if (! $this->isSpatieTranslatable() || blank($this->pendingSpatieTranslations)) {
            return;
        }

        $record = $this->getCachedExistingRecord();

        if (! $record instanceof SeoMetadata) {
            return;
        }

        foreach ($this->pendingSpatieTranslations as $field => $localeMap) {
            $record->setTranslations($field, $localeMap);
        }

        $record->save();
        $this->cachedExistingRecord($record);
        $this->pendingSpatieTranslations = null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function stripLocaleTabbedFields(array $data): array
    {
        foreach (SeoMetadata::make()->getTranslatableAttributes() as $field) {
            unset($data[$field]);

            foreach ($this->getLocales() as $locale) {
                unset($data["{$field}.{$locale}"]);
            }
        }

        return $data;
    }

    protected function readSpatieSourceValue(Get $get, string $fieldName, string $locale): string
    {
        $record = $this->getRecord();

        if (
            $record instanceof Model
            && in_array(HasTranslations::class, class_uses_recursive($record), true)
            && $record->isTranslatableAttribute($fieldName)
        ) {
            return $this->stringifySourceValue(
                $record->getTranslation($fieldName, $locale, useFallbackLocale: false),
                $locale,
            );
        }

        $value = $get($fieldName);

        if (is_array($value) && $this->isLocaleMap($value)) {
            return $this->stringifySourceValue($value[$locale] ?? null, $locale);
        }

        return $this->stringifySourceValue($value, $locale);
    }

    /**
     * @param  array<mixed, mixed>  $value
     */
    protected function isLocaleMap(array $value): bool
    {
        if ($value === []) {
            return false;
        }

        return collect(array_keys($value))->every(static fn (mixed $key): bool => is_string($key));
    }

    protected function setSeoField(Set $set, string $field, mixed $value): void
    {
        $set($this->seoFieldStatePath($field), $value, isAbsolute: true, shouldCallUpdatedHooks: true);
    }

    protected function getSeoField(Get $get, string $field): mixed
    {
        return $get($this->seoFieldStatePath($field), isAbsolute: true);
    }

    protected function seoFieldStatePath(string $field): string
    {
        $statePath = $this->getStatePath();

        return filled($statePath) ? "{$statePath}.{$field}" : $field;
    }

    public function resolveLocalizedValue(Get $get, string $field, string $locale): string
    {
        if ($this->usesLocaleTabs()) {
            $value = $this->getSeoField($get, "{$field}.{$locale}");

            if ($field === 'keywords') {
                return implode(', ', SeoMetadata::normalizeKeywords($value));
            }

            return $this->stringifySourceValue($value);
        }

        $value = $this->getSeoField($get, $field);

        if ($field === 'keywords') {
            return implode(', ', SeoMetadata::normalizeKeywords($value));
        }

        return is_string($value) ? $value : '';
    }

    public function resolveActiveSeoTabLocale(): string
    {
        $locales = $this->getLocales();
        $tabs = $this->findSeoLocalesTabs();

        if ($tabs !== null) {
            $index = max(0, $tabs->getActiveTab() - 1);

            return $locales[$index] ?? Arr::first($locales) ?? app()->getLocale();
        }

        return Arr::first($locales) ?? app()->getLocale();
    }

    protected function findSeoLocalesTabs(): ?Tabs
    {
        $childSchema = $this->getChildSchema();

        if ($childSchema === null) {
            return null;
        }

        foreach ($childSchema->getComponents(withActions: false, withHidden: true) as $component) {
            if ($component instanceof Tabs) {
                return $component;
            }
        }

        return null;
    }

    public function resolveActiveLocale(): string
    {
        if ($this->usesLocaleTabs()) {
            return $this->resolveActiveSeoTabLocale();
        }

        return Arr::first($this->getLocales()) ?? app()->getLocale();
    }

    /**
     * @return list<string>
     */
    protected function resolveSourceFieldNames(string $baseFieldName): array
    {
        $fieldNames = [];

        foreach ($this->getLocales() as $locale) {
            $suffixedFieldName = $this->buildLocaleSuffixedFieldName($baseFieldName, $locale);

            if ($this->findRootField($suffixedFieldName) !== null) {
                $fieldNames[] = $suffixedFieldName;
            }
        }

        if ($fieldNames === [] && $this->findRootField($baseFieldName) !== null) {
            $fieldNames[] = $baseFieldName;
        }

        return $fieldNames;
    }

    protected function resolveSourceFieldNameForLocale(string $baseFieldName, string $locale): string
    {
        $suffixedFieldName = $this->buildLocaleSuffixedFieldName($baseFieldName, $locale);

        return $this->findRootField($suffixedFieldName) !== null
            ? $suffixedFieldName
            : $baseFieldName;
    }

    protected function buildLocaleSuffixedFieldName(string $baseFieldName, string $locale): string
    {
        return "{$baseFieldName}_{$locale}";
    }
}
