<?php

declare(strict_types=1);

namespace Martin6363\FilamentSmartSeo\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Martin6363\FilamentSmartSeo\Models\SeoMetadata;

/**
 * @mixin Model
 *
 * @property-read SeoMetadata|null $seo
 */
trait HasSeo
{
    public static function bootHasSeo(): void
    {
        static::deleting(function (Model $model): void {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            $model->seo()->delete();
        });
    }

    /**
     * @return MorphOne<SeoMetadata, $this>
     */
    public function seo(): MorphOne
    {
        return $this->morphOne(SeoMetadata::class, 'seoble');
    }

    /**
     * Convenience accessor for the related SEO row, creating a blank instance when missing.
     */
    public function seoOrNew(): SeoMetadata
    {
        return $this->seo()->firstOrNew([]);
    }
}
