<?php

declare(strict_types=1);

namespace Martin6363\FilamentSmartSeo;

use Illuminate\Support\ServiceProvider;
use Martin6363\FilamentSmartSeo\Contracts\SeoSettingsStore;
use Martin6363\FilamentSmartSeo\Services\GeminiSeoService;
use Martin6363\FilamentSmartSeo\Stores\DatabaseSeoSettingsStore;

class FilamentSmartSeoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/filament-smart-seo.php',
            'filament-smart-seo',
        );

        $this->app->singleton(FilamentSmartSeoPlugin::class);

        $this->app->singleton(GeminiSeoService::class, function (): GeminiSeoService {
            return new GeminiSeoService(
                apiKey: config('filament-smart-seo.api_key'),
                model: (string) config('filament-smart-seo.gemini_model', 'gemini-2.5-flash'),
                maxTitleLength: (int) config('filament-smart-seo.max_title_length', 60),
                maxDescriptionLength: (int) config('filament-smart-seo.max_description_length', 160),
            );
        });

        $this->app->singleton(SeoSettingsStore::class, function (): SeoSettingsStore {
            $storeClass = config('filament-smart-seo.settings_store', DatabaseSeoSettingsStore::class);

            return $this->app->make($storeClass);
        });
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'filament-smart-seo');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'filament-smart-seo');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/filament-smart-seo.php' => config_path('filament-smart-seo.php'),
            ], 'filament-smart-seo-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'filament-smart-seo-migrations');

            $this->publishes([
                __DIR__.'/../lang' => $this->app->langPath('vendor/filament-smart-seo'),
            ], 'filament-smart-seo-translations');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/filament-smart-seo'),
            ], 'filament-smart-seo-views');
        }
    }
}
