<?php

declare(strict_types=1);

namespace Martin6363\FilamentSmartSeo\Services;

use Gemini;
use Gemini\Data\GenerationConfig;
use Gemini\Enums\ResponseMimeType;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JsonException;
use Martin6363\FilamentSmartSeo\Models\SeoMetadata;
use RuntimeException;
use Throwable;

class GeminiSeoService
{
    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $model,
        private readonly int $maxTitleLength = 60,
        private readonly int $maxDescriptionLength = 160,
    ) {}

    /**
     * Generate optimized SEO metadata from mapped source context.
     *
     * @return array{title: string, description: string, keywords: list<string>}
     */
    public function generate(
        string $sourceTitle,
        string $sourceDescription,
        string $locale = 'en',
    ): array {
        if (blank($this->apiKey)) {
            throw new RuntimeException('Gemini API key is not configured. Set GEMINI_API_KEY in your .env file.');
        }

        $sourceTitle = $this->normalizeContext($sourceTitle);
        $sourceDescription = $this->normalizeContext($sourceDescription);

        if (blank($sourceTitle) && blank($sourceDescription)) {
            throw new RuntimeException('There is no source title or description content to audit.');
        }

        $modelsToTry = array_values(array_filter(array_unique([
            $this->model,
            'gemini-2.5-flash',
            'gemini-2.0-flash',
            'gemini-1.5-flash',
            'gemini-1.5-flash-8b'
        ])));

        $lastException = null;

        foreach ($modelsToTry as $currentModel) {
            try {
                $response = Gemini::client($this->apiKey)
                    ->generativeModel(model: $currentModel)
                    ->withGenerationConfig(new GenerationConfig(
                        responseMimeType: ResponseMimeType::APPLICATION_JSON,
                    ))
                    ->generateContent($this->buildPrompt($sourceTitle, $sourceDescription, $locale));

                return $this->parseResponse($response->text());
            } catch (Throwable $exception) {
                $lastException = $exception;

                if (! str_contains(strtolower($exception->getMessage()), 'demand') &&
                    ! str_contains(strtolower($exception->getMessage()), 'quota')) {
                    break;
                }

                Log::warning("Filament Smart SEO: Model [{$currentModel}] busy, trying fallback...");
            }
        }

        Log::error('Filament Smart SEO: Gemini API request failed after trying all fallbacks.', [
            'exception' => $lastException?->getMessage(),
            'locale' => $locale,
        ]);

        throw new RuntimeException(
            'SEO generation failed (AI servers are busy): '.($lastException?->getMessage() ?? 'Unknown error'),
            previous: $lastException,
        );
    }

    /**
     * Keywords are always derived from the mapped title / description context.
     *
     * @return list<string>
     */
    public function extractKeywords(
        string $sourceTitle,
        string $sourceDescription,
        string $locale = 'en',
    ): array {
        return $this->generate($sourceTitle, $sourceDescription, $locale)['keywords'];
    }

    private function buildPrompt(string $sourceTitle, string $sourceDescription, string $locale): string
    {
        $titleLimit = $this->maxTitleLength;
        $descriptionLimit = $this->maxDescriptionLength;

        $titleContext = filled($sourceTitle) ? $sourceTitle : '(empty)';
        $descriptionContext = filled($sourceDescription) ? $sourceDescription : '(empty)';

        return <<<PROMPT
            You are a senior SEO specialist. Analyze the source content and produce optimized metadata for locale "{$locale}".

            Rules:
            - Return ONLY a flat JSON object with keys: "title", "description", "keywords".
            - "title" must be a compelling SEO title, max {$titleLimit} characters.
            - "description" must be a persuasive meta description, max {$descriptionLimit} characters.
            - "keywords" must be an array of 5 to 10 concise keyword phrases, always extracted and optimized from the source context.
            - Write naturally for locale "{$locale}" (Armenian, English, Chinese, etc. as applicable).
            - Do not wrap the JSON in markdown code fences.
            - Do not invent unrelated topics.

            Source title context:
            {$titleContext}

            Source description / body context:
            {$descriptionContext}
            PROMPT;
    }

    /**
     * @return array{title: string, description: string, keywords: list<string>}
     */
    private function parseResponse(string $rawResponse): array
    {
        $rawResponse = trim($rawResponse);

        if (preg_match('/```(?:json)?\s*(.*?)\s*```/s', $rawResponse, $matches) === 1) {
            $rawResponse = trim($matches[1]);
        }

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($rawResponse, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Gemini returned an invalid JSON response.', previous: $exception);
        }

        if (! is_array($decoded)) {
            throw new RuntimeException('Gemini returned an unexpected response format.');
        }

        $title = trim((string) Arr::get($decoded, 'title', ''));
        $description = trim((string) Arr::get($decoded, 'description', ''));
        $keywords = SeoMetadata::normalizeKeywords(Arr::get($decoded, 'keywords'));

        if (blank($title) && blank($description) && $keywords === []) {
            throw new RuntimeException('Gemini did not return usable SEO metadata.');
        }

        return [
            'title' => Str::limit($title, $this->maxTitleLength, ''),
            'description' => Str::limit($description, $this->maxDescriptionLength, ''),
            'keywords' => $keywords,
        ];
    }

    private function normalizeContext(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }
}
