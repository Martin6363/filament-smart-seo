@php
    $maxTitleLength = (int) ($maxTitleLength ?? config('filament-smart-seo.max_title_length', 60));
    $maxDescriptionLength = (int) ($maxDescriptionLength ?? config('filament-smart-seo.max_description_length', 160));
    $previewUrl = $url ?? config('filament-smart-seo.preview_base_url', url('/'));

    $locale = $previewLocale ?? $locale ?? app()->getLocale();
    $isLocaleSuffixed = (bool) ($isLocaleSuffixed ?? false);
    $usesTranslatableMap = (bool) ($usesTranslatableMap ?? false);
    $syncActiveLocaleFromLivewire = (bool) ($syncActiveLocaleFromLivewire ?? false);
    $fixedPreviewLocale = filled($previewLocale ?? null) ? (string) $previewLocale : null;

    if (isset($field)) {
        if ($fixedPreviewLocale !== null) {
            $titleStatePath = $field->resolveRelativeStatePath("title.{$fixedPreviewLocale}");
            $descriptionStatePath = $field->resolveRelativeStatePath("description.{$fixedPreviewLocale}");
        } elseif ($usesTranslatableMap) {
            $titleStatePath ??= $field->resolveRelativeStatePath('title');
            $descriptionStatePath ??= $field->resolveRelativeStatePath('description');
        } else {
            $titleStatePath ??= $field->resolveRelativeStatePath('title');
            $descriptionStatePath ??= $field->resolveRelativeStatePath('description');
        }
    }

    $titleStatePath ??= 'data.seo.title';
    $descriptionStatePath ??= 'data.seo.description';

    $fallbackTitle = (string) config('filament-smart-seo.preview_fallback_title', 'Page title');
    $fallbackDescription = (string) config('filament-smart-seo.preview_fallback_description', 'Your meta description will appear here. Write a compelling summary that encourages clicks from search results.');

    $parsedUrl = parse_url($previewUrl) ?: [];
    $host = $parsedUrl['host'] ?? parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';
    $path = $parsedUrl['path'] ?? '/';
    $pathSegments = array_values(array_filter(explode('/', trim($path, '/'))));

    if (isset($record) && is_object($record) && filled($record->slug ?? null)) {
        $pathSegments[] = (string) $record->slug;
    }

    $breadcrumb = collect([$host, ...$pathSegments])->unique()->take(5)->implode(' > ');

    $faviconLetter = strtoupper(mb_substr($host, 0, 1));

    $showMobileOgPreview = (bool) ($showMobileOgPreview ?? false);
    $ogImageStatePath = $ogImageStatePath ?? null;
    $ogImageBaseUrl = rtrim((string) ($ogImageBaseUrl ?? ''), '/');
    $siteName = (string) ($siteName ?? strtoupper(preg_replace('/^www\./i', '', $host)));
@endphp

<div
    class="fi-fo-seo-preview"
    style="margin-top: 24px; font-family: Arial, sans-serif;"
    x-data="{
        titleState: $wire.$entangle(@js($titleStatePath)).live,
        descriptionState: $wire.$entangle(@js($descriptionStatePath)).live,
        @if ($syncActiveLocaleFromLivewire)
        activeLocale: $wire.$entangle('activeLocale').live,
        @else
        activeLocale: @js($locale),
        @endif
        fixedPreviewLocale: @js($fixedPreviewLocale),
        usesTranslatableMap: @js($usesTranslatableMap),
        fallbackTitle: @js($fallbackTitle),
        fallbackDescription: @js($fallbackDescription),
        maxTitleLength: @js($maxTitleLength),
        maxDescriptionLength: @js($maxDescriptionLength),

        resolveString(state) {
            if (state === null || state === undefined) {
                return '';
            }

            if (typeof state === 'object') {
                if (Array.isArray(state)) {
                    return '';
                }

                const locale = this.fixedPreviewLocale ?? this.activeLocale;

                if (state[locale] !== undefined && state[locale] !== null) {
                    return String(state[locale]).trim();
                }

                const first = Object.values(state).find((value) => value !== null && value !== undefined && String(value).trim() !== '');

                return first !== undefined ? String(first).trim() : '';
            }

            return String(state).trim();
        },

        get rawTitle() {
            return this.resolveString(this.titleState);
        },

        get rawDescription() {
            return this.resolveString(this.descriptionState);
        },

        get displayTitle() {
            return this.rawTitle !== '' ? this.rawTitle : this.fallbackTitle;
        },

        get displayDescription() {
            return this.rawDescription !== '' ? this.rawDescription : this.fallbackDescription;
        },

        get titleLength() {
            return this.rawTitle.length;
        },

        get descriptionLength() {
            return this.rawDescription.length;
        },

        get titleBadgeStyle() {
            if (this.titleLength >= 40 && this.titleLength <= this.maxTitleLength) {
                return 'background-color: #e6f4ea; color: #137333; border: 1px solid #ceead6;';
            }

            return 'background-color: #fce8e6; color: #c5221f; border: 1px solid #f6aea9;';
        },

        get descriptionBadgeStyle() {
            if (this.descriptionLength >= 130 && this.descriptionLength <= this.maxDescriptionLength) {
                return 'background-color: #e6f4ea; color: #137333; border: 1px solid #ceead6;';
            }

            return 'background-color: #fef7e0; color: #b06000; border: 1px solid #feefc3;';
        },
        @if ($showMobileOgPreview)
        @if (filled($ogImageStatePath))
        ogImageState: $wire.$entangle(@js($ogImageStatePath)).live,
        @endif
        ogImageBaseUrl: @js($ogImageBaseUrl),
        siteName: @js($siteName),

        resolveOgImagePath(state) {
            if (state === null || state === undefined || state === '') {
                return '';
            }

            if (Array.isArray(state)) {
                const first = state.find((value) => value !== null && value !== undefined && String(value).trim() !== '');

                return first !== undefined ? String(first).trim() : '';
            }

            if (typeof state === 'object') {
                const first = Object.values(state).find((value) => value !== null && value !== undefined && String(value).trim() !== '');

                return first !== undefined ? String(first).trim() : '';
            }

            return String(state).trim();
        },

        get ogImageUrl() {
            @if (! filled($ogImageStatePath))
            return null;
            @endif

            const path = this.resolveOgImagePath(this.ogImageState);

            if (path === '') {
                return null;
            }

            if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('data:') || path.startsWith('blob:')) {
                return path;
            }

            const base = this.ogImageBaseUrl.replace(/\/$/, '');
            const normalizedPath = path.replace(/^\//, '');

            return base !== '' ? `${base}/${normalizedPath}` : normalizedPath;
        },
        @endif
    }"
>
    <div
        style="
            max-width: 600px;
            padding: 16px 0 12px;
        "
    >
        {{-- Row 1: Favicon + breadcrumb URL --}}
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 4px;">
            <span
                aria-hidden="true"
                style="
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    width: 26px;
                    height: 26px;
                    border-radius: 50%;
                    background-color: #e8eaed;
                    color: #5f6368;
                    font-size: 12px;
                    font-weight: 500;
                    flex-shrink: 0;
                    line-height: 1;
                "
            >
                {{ $faviconLetter }}
            </span>

            <span
                style="
                    color: #4d5156;
                    font-size: 14px;
                    line-height: 1.3;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                "
            >
                {{ $breadcrumb }}
            </span>
        </div>

        {{-- Row 2: Clickable search title --}}
        <div
            x-text="displayTitle"
            style="
                color: #1a0dab;
                font-size: 20px;
                line-height: 1.3;
                font-weight: 400;
                margin: 0 0 4px;
                cursor: pointer;
                word-break: break-word;
            "
            x-bind:style="{ opacity: rawTitle === '' ? '0.55' : '1' }"
        ></div>

        {{-- Row 3: Meta description --}}
        <div
            x-text="displayDescription"
            style="
                color: #4d5156;
                font-size: 14px;
                line-height: 1.57;
                margin: 0;
                word-break: break-word;
            "
            x-bind:style="{ opacity: rawDescription === '' ? '0.55' : '1' }"
        ></div>
    </div>

    {{-- SEO length counter badges --}}
    <div
        style="
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        "
    >
        <span
            x-text="`${titleLength} / ${maxTitleLength}`"
            x-bind:style="titleBadgeStyle + ' display: inline-block; padding: 4px 10px; border-radius: 9999px; font-size: 12px; font-weight: 600; line-height: 1.4;'"
            title="{{ __('filament-smart-seo::fields.title') }}"
        ></span>

        <span
            x-text="`${descriptionLength} / ${maxDescriptionLength}`"
            x-bind:style="descriptionBadgeStyle + ' display: inline-block; padding: 4px 10px; border-radius: 9999px; font-size: 12px; font-weight: 600; line-height: 1.4;'"
            title="{{ __('filament-smart-seo::fields.description') }}"
        ></span>
    </div>

    @if ($showMobileOgPreview)
        <div
            style="
                margin-top: 28px;
                padding-top: 24px;
                border-top: 1px solid #e5e7eb;
            "
        >
            <div
                style="
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    margin-bottom: 14px;
                "
            >
                <span
                    aria-hidden="true"
                    style="
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        width: 28px;
                        height: 28px;
                        border-radius: 8px;
                        background: linear-gradient(135deg, #1877f2 0%, #0a5bd3 100%);
                        color: #ffffff;
                        flex-shrink: 0;
                    "
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="16" height="16" aria-hidden="true">
                        <path d="M17 1.01 7 1c-1.1 0-2 .9-2 2v18c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V3c0-1.1-.9-2-2-2zM7 19V5h10v14H7z"/>
                    </svg>
                </span>

                <span
                    style="
                        color: #374151;
                        font-size: 13px;
                        font-weight: 600;
                        letter-spacing: 0.01em;
                    "
                >
                    {{ __('filament-smart-seo::fields.mobile_og_preview') }}
                </span>
            </div>

            <div
                style="
                    max-width: 360px;
                    border-radius: 12px;
                    overflow: hidden;
                    border: 1px solid #dadde1;
                    background-color: #ffffff;
                    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06), 0 4px 12px rgba(0, 0, 0, 0.04);
                "
            >
                <div
                    style="
                        position: relative;
                        width: 100%;
                        aspect-ratio: 1.91 / 1;
                        background: linear-gradient(145deg, #f3f4f6 0%, #e5e7eb 100%);
                        overflow: hidden;
                    "
                >
                    <template x-if="ogImageUrl">
                        <img
                            :src="ogImageUrl"
                            alt=""
                            style="
                                position: absolute;
                                inset: 0;
                                width: 100%;
                                height: 100%;
                                object-fit: cover;
                            "
                        />
                    </template>

                    <template x-if="! ogImageUrl">
                        <div
                            style="
                                position: absolute;
                                inset: 0;
                                display: flex;
                                flex-direction: column;
                                align-items: center;
                                justify-content: center;
                                gap: 8px;
                                color: #9ca3af;
                            "
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="36" height="36" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z" />
                            </svg>

                            <span style="font-size: 12px; font-weight: 500;">
                                {{ __('filament-smart-seo::fields.mobile_og_image_placeholder') }}
                            </span>
                        </div>
                    </template>
                </div>

                <div style="padding: 12px 14px 14px; background-color: #f0f2f5;">
                    <div
                        x-text="siteName"
                        style="
                            color: #65676b;
                            font-size: 12px;
                            line-height: 1.3;
                            text-transform: uppercase;
                            letter-spacing: 0.04em;
                            margin-bottom: 6px;
                            overflow: hidden;
                            text-overflow: ellipsis;
                            white-space: nowrap;
                        "
                    ></div>

                    <div
                        x-text="displayTitle"
                        style="
                            color: #050505;
                            font-size: 16px;
                            font-weight: 600;
                            line-height: 1.35;
                            margin-bottom: 4px;
                            display: -webkit-box;
                            -webkit-line-clamp: 2;
                            -webkit-box-orient: vertical;
                            overflow: hidden;
                            word-break: break-word;
                        "
                        x-bind:style="{ opacity: rawTitle === '' ? '0.55' : '1' }"
                    ></div>

                    <div
                        x-text="displayDescription"
                        style="
                            color: #65676b;
                            font-size: 14px;
                            line-height: 1.4;
                            display: -webkit-box;
                            -webkit-line-clamp: 2;
                            -webkit-box-orient: vertical;
                            overflow: hidden;
                            word-break: break-word;
                        "
                        x-bind:style="{ opacity: rawDescription === '' ? '0.55' : '1' }"
                    ></div>
                </div>
            </div>
        </div>
    @endif
</div>
