<?php

declare(strict_types=1);

namespace Martin6363\FilamentSmartSeo\Contracts;

interface SeoSettingsStore
{
    /**
     * @return array{
     *     title?: array<string, string>|string|null,
     *     description?: array<string, string>|string|null,
     *     keywords?: array<string, array<int, string>|string>|array<int, string>|string|null,
     *     og_image?: string|null
     * }|null
     */
    public function get(string $key): ?array;

    /**
     * @param  array{
     *     title?: array<string, string>|string|null,
     *     description?: array<string, string>|string|null,
     *     keywords?: array<string, array<int, string>|string>|array<int, string>|string|null,
     *     og_image?: string|null
     * }  $data
     */
    public function put(string $key, array $data): void;

    public function forget(string $key): void;
}
