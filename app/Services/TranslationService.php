<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class TranslationService
{
    private const GLOSSARY = [
        'brake pads' => 'klocki hamulcowe',
        'clutch' => 'sprzęgło',
        'timing belt' => 'pasek rozrządu',
        'cam belt' => 'pasek rozrządu',
        'MOT' => 'badanie techniczne',
        'exhaust' => 'układ wydechowy',
        'alternator' => 'alternator',
        'starter motor' => 'rozrusznik',
        'head gasket' => 'uszczelka pod głowicą',
        'wheel bearing' => 'łożysko koła',
        'shock absorber' => 'amortyzator',
        'CV joint' => 'przegub homokinetyczny',
        'catalytic converter' => 'katalizator',
        'turbocharger' => 'turbosprężarka',
        'fuel pump' => 'pompa paliwa',
    ];

    public function needsTranslation(string $mechanicLocale, string $customerLocale): bool
    {
        return $mechanicLocale !== $customerLocale;
    }

    public function translate(
        string $text,
        string $fromLocale,
        string $toLocale,
        string $context = 'general'
    ): string {
        if (! $this->needsTranslation($fromLocale, $toLocale)) {
            return $text;
        }

        $cacheKey = 'translation:' . md5("{$fromLocale}:{$toLocale}:{$context}:{$text}");

        return Cache::remember($cacheKey, now()->addDay(), function () use ($text, $fromLocale, $toLocale, $context) {
            return $this->callLlm($text, $fromLocale, $toLocale, $context);
        });
    }

    public function previewEstimateTranslation(array $lineItems, string $fromLocale, string $toLocale): array
    {
        return array_map(function (array $item) use ($fromLocale, $toLocale) {
            return [
                'id' => $item['id'],
                'original' => $item['description'],
                'translated' => $this->translate($item['description'], $fromLocale, $toLocale, 'estimate'),
                'price' => $item['price'],
            ];
        }, $lineItems);
    }

    private function callLlm(string $text, string $from, string $to, string $context): string
    {
        $glossaryLines = collect(self::GLOSSARY)
            ->map(fn ($pl, $en) => "- {$en} → {$pl}")
            ->implode("\n");

        $systemPrompt = 'You are a professional automotive translator working in a UK garage context. '
            . "Translate the following text from {$from} to {$to}. "
            . 'Use professional, clear language appropriate for customer communications. '
            . 'Preserve all technical part names exactly. '
            . ($context === 'estimate' ? 'This is a repair estimate line item — accuracy of part names and prices is critical. ' : '')
            . "Automotive glossary (do not deviate from these terms):\n{$glossaryLines}\n"
            . 'Return only the translated text, nothing else.';

        $response = Http::withHeader('Authorization', 'Bearer ' . config('services.openai.key'))
            ->timeout(30)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('services.openai.translation_model', 'gpt-4o-mini'),
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $text],
                ],
                'max_tokens' => 500,
                'temperature' => 0.1,
            ]);

        if (! $response->successful()) {
            return $text;
        }

        return trim($response->json('choices.0.message.content', $text));
    }
}
