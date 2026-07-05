<?php

declare(strict_types=1);

namespace Martin6363\FilamentSmartSeo\Concerns;

use Martin6363\FilamentSmartSeo\Contracts\SeoSettingsStore;
use Martin6363\FilamentSmartSeo\Forms\Components\SeoSection;
use Martin6363\FilamentSmartSeo\Models\SeoMetadata;

/**
 * Bind SEO form state on custom Filament pages that have no Eloquent model.
 *
 * Pair with `SeoSection::make()->settingsKey('site.seo')` and call the helpers
 * from `mount()` / `save()` (or your page's fill / persist hooks).
 */
trait InteractsWithSeoSettings
{
    /**
     * @return array<string, mixed>
     */
    protected function getSeoSettingsFormData(): array
    {
        $data = [];

        foreach ($this->resolveSeoSettingsKeys() as $statePath => $settingsKey) {
            $payload = $this->seoSettingsStore()->get($settingsKey) ?? [
                'title' => [],
                'description' => [],
                'keywords' => [],
                'og_image' => null,
            ];

            data_set($data, $statePath, $payload);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function saveSeoSettingsFromFormData(array $data): void
    {
        foreach ($this->resolveSeoSettingsKeys() as $statePath => $settingsKey) {
            /** @var array<string, mixed> $payload */
            $payload = data_get($data, $statePath, []);

            $this->seoSettingsStore()->put($settingsKey, [
                'title' => $payload['title'] ?? [],
                'description' => $payload['description'] ?? [],
                'keywords' => $payload['keywords'] ?? [],
                'og_image' => $payload['og_image'] ?? null,
            ]);
        }
    }

    /**
     * Override to declare settings keys when you prefer not to scan the schema.
     *
     * @return array<string, string> form state path => settings key
     */
    protected function getSeoSettingsKeys(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    protected function resolveSeoSettingsKeys(): array
    {
        $explicit = $this->getSeoSettingsKeys();

        if ($explicit !== []) {
            return $explicit;
        }

        return $this->discoverSeoSettingsKeysFromForm();
    }

    /**
     * @return array<string, string>
     */
    protected function discoverSeoSettingsKeysFromForm(): array
    {
        $schema = null;

        if (method_exists($this, 'getSchema')) {
            $schema = $this->getSchema('form');
        } elseif (method_exists($this, 'getForm')) {
            $schema = $this->getForm('form');
        }

        if ($schema === null) {
            return [];
        }

        $keys = [];

        foreach ($schema->getFlatComponents(withActions: false, withHidden: true) as $component) {
            if (! $component instanceof SeoSection) {
                continue;
            }

            $settingsKey = $component->getSettingsKey();

            if (blank($settingsKey)) {
                continue;
            }

            $keys[$component->getStatePath()] = $settingsKey;
        }

        return $keys;
    }

    protected function seoSettingsStore(): SeoSettingsStore
    {
        return app(SeoSettingsStore::class);
    }

    /**
     * Read a single localized SEO value from the settings store.
     */
    protected function getSeoSetting(string $settingsKey, string $attribute, ?string $locale = null): mixed
    {
        $payload = $this->seoSettingsStore()->get($settingsKey);

        if ($payload === null) {
            return null;
        }

        $value = $payload[$attribute] ?? null;
        $locale ??= app()->getLocale();

        if ($attribute === 'keywords') {
            if (is_array($value) && array_key_exists($locale, $value)) {
                return SeoMetadata::normalizeKeywords($value[$locale]);
            }

            return SeoMetadata::normalizeKeywords($value);
        }

        if (is_array($value)) {
            return $value[$locale] ?? null;
        }

        return $value;
    }
}
