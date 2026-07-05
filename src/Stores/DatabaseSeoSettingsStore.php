<?php

declare(strict_types=1);

namespace Martin6363\FilamentSmartSeo\Stores;

use Martin6363\FilamentSmartSeo\Contracts\SeoSettingsStore;
use Martin6363\FilamentSmartSeo\Models\SeoMetadata;

/**
 * Persists SEO payloads for custom Filament pages (no Eloquent parent model)
 * inside the shared `seo_metadata` table using the `settings_key` column.
 */
class DatabaseSeoSettingsStore implements SeoSettingsStore
{
    public function get(string $key): ?array
    {
        $record = $this->find($key);

        if ($record === null) {
            return null;
        }

        return [
            'title' => $record->getTranslations('title'),
            'description' => $record->getTranslations('description'),
            'keywords' => $record->getTranslations('keywords'),
            'og_image' => $record->og_image,
        ];
    }

    public function put(string $key, array $data): void
    {
        $record = $this->find($key) ?? new SeoMetadata([
            'settings_key' => $key,
        ]);

        if (array_key_exists('title', $data)) {
            $record->setTranslations('title', $this->normalizeTranslationMap($data['title']));
        }

        if (array_key_exists('description', $data)) {
            $record->setTranslations('description', $this->normalizeTranslationMap($data['description']));
        }

        if (array_key_exists('keywords', $data)) {
            $record->setTranslations('keywords', $this->normalizeKeywordsMap($data['keywords']));
        }

        if (array_key_exists('og_image', $data)) {
            $record->og_image = is_string($data['og_image']) ? $data['og_image'] : null;
        }

        $record->save();
    }

    public function forget(string $key): void
    {
        $this->find($key)?->delete();
    }

    protected function find(string $key): ?SeoMetadata
    {
        return SeoMetadata::query()
            ->where('settings_key', $key)
            ->first();
    }

    /**
     * @return array<string, string>
     */
    protected function normalizeTranslationMap(mixed $value): array
    {
        if (is_string($value)) {
            return [app()->getLocale() => $value];
        }

        if (! is_array($value)) {
            return [];
        }

        $map = [];

        foreach ($value as $locale => $text) {
            if (! is_string($locale)) {
                continue;
            }

            $map[$locale] = is_string($text) ? $text : (is_scalar($text) ? (string) $text : '');
        }

        return $map;
    }

    /**
     * @return array<string, list<string>>
     */
    protected function normalizeKeywordsMap(mixed $value): array
    {
        if (is_string($value) || (is_array($value) && array_is_list($value))) {
            return [app()->getLocale() => SeoMetadata::normalizeKeywords($value)];
        }

        if (! is_array($value)) {
            return [];
        }

        $map = [];

        foreach ($value as $locale => $keywords) {
            if (! is_string($locale)) {
                continue;
            }

            $map[$locale] = SeoMetadata::normalizeKeywords($keywords);
        }

        return $map;
    }
}
