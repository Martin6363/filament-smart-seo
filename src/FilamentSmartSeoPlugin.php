<?php

declare(strict_types=1);

namespace Martin6363\FilamentSmartSeo;

use Filament\Contracts\Plugin;
use Filament\Panel;

class FilamentSmartSeoPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'filament-smart-seo';
    }

    public function register(Panel $panel): void
    {
        //
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
