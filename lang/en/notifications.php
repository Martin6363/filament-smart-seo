<?php

declare(strict_types=1);

return [
    'empty_source' => [
        'title' => 'Nothing to audit',
        'body' => 'Fill the mapped source title or description field before generating SEO metadata.',
    ],
    'generating' => [
        'title' => 'Generating SEO…',
        'body' => 'Gemini is analyzing your content and optimizing titles, descriptions, and keywords.',
    ],
    'success' => [
        'title' => 'SEO metadata ready',
        'body' => 'Title, description, and keywords were updated successfully.',
    ],
    'failed' => [
        'title' => 'SEO generation failed',
    ],
];
