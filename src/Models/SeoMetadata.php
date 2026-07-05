<?php

declare(strict_types=1);

namespace Martin6363\FilamentSmartSeo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property string|null $seoble_type
 * @property int|null $seoble_id
 * @property string|null $settings_key
 * @property array<string, string>|string|null $title
 * @property array<string, string>|string|null $description
 * @property array<string, array<int, string>|string>|array<int, string>|string|null $keywords
 * @property string|null $og_image
 */
class SeoMetadata extends Model
{
    use HasTranslations;

    protected $table = 'seo_metadata';

    /**
     * @var list<string>
     */
    public array $translatable = [
        'title',
        'description',
        'keywords',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'seoble_type',
        'seoble_id',
        'settings_key',
        'title',
        'description',
        'keywords',
        'og_image',
    ];

    /**
     * @return MorphTo<Model, $this>
     */
    public function seoble(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Normalize keywords for a locale into a flat list of tags.
     *
     * @return list<string>
     */
    public function keywordsForLocale(?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $value = $this->getTranslation('keywords', $locale, useFallbackLocale: false);

        return self::normalizeKeywords($value);
    }

    /**
     * @return list<string>
     */
    public static function normalizeKeywords(mixed $value): array
    {
        if (is_array($value)) {
            $isLocaleMap = array_keys($value) !== range(0, count($value) - 1)
                && collect($value)->keys()->every(static fn (mixed $key): bool => is_string($key));

            if ($isLocaleMap) {
                return [];
            }

            return array_values(array_filter(array_map(
                static fn (mixed $item): string => trim((string) $item),
                $value,
            )));
        }

        if (is_string($value) && filled($value)) {
            return array_values(array_filter(array_map(
                static fn (string $item): string => trim($item),
                preg_split('/\s*,\s*/', $value) ?: [],
            )));
        }

        return [];
    }
}
