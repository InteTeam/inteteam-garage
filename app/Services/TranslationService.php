<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class TranslationService
{
    public const SUPPORTED_LOCALES = ['en', 'pl'];


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
            $translated = $this->translate($item['description'], $fromLocale, $toLocale, 'estimate');

            return [
                'id' => $item['id'],
                'original' => $item['description'],
                'translated' => $translated,
                'translated_by_ai' => $translated !== $item['description'],
                'price' => $item['price'],
            ];
        }, $lineItems);
    }

    public function detectLanguage(string $text): ?string
    {
        if (trim($text) === '') {
            return null;
        }

        $cacheKey = 'translation:detect:' . md5($text);

        return Cache::remember($cacheKey, now()->addDay(), function () use ($text) {
            return $this->callDetect($text);
        });
    }

    public function verifySourceLocale(string $configured, string $sampleText): string
    {
        $detected = $this->detectLanguage($sampleText);

        if ($detected === null || $detected === $configured) {
            return $configured;
        }

        Log::warning('Source locale disagreement: detected language differs from configured', [
            'configured' => $configured,
            'detected' => $detected,
            'sample_excerpt' => mb_substr($sampleText, 0, 60),
        ]);

        return $detected;
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

    private function callDetect(string $text): ?string
    {
        $supported = implode(', ', self::SUPPORTED_LOCALES);

        $systemPrompt = 'You are a language detector. Identify the ISO 639-1 code of the source language of the user input. '
            . "Only return a single lowercase code from this set: {$supported}. "
            . "If you cannot determine the language with confidence, return 'unknown'. "
            . 'Output the code only, no commentary, no quotes.';

        $response = Http::withHeader('Authorization', 'Bearer ' . config('services.openai.key'))
            ->timeout(15)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('services.openai.detect_model', 'gpt-4o-mini'),
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $text],
                ],
                'max_tokens' => 4,
                'temperature' => 0.0,
            ]);

        if (! $response->successful()) {
            return null;
        }

        $code = strtolower(trim((string) $response->json('choices.0.message.content', '')));

        return in_array($code, self::SUPPORTED_LOCALES, true) ? $code : null;
    }
}
