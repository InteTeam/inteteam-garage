<?php

declare(strict_types=1);

namespace Tests\Feature\Translation;

use App\Services\TranslationService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class GlossaryTest extends TestCase
{
    public function test_every_glossary_pair_appears_in_system_prompt(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'translated']]],
            ], 200),
        ]);

        (new TranslationService)->translate('Replaced the brake pads', 'en', 'pl');

        $glossary = [
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

        Http::assertSent(function (Request $request) use ($glossary): bool {
            $systemMessage = $this->extractSystemMessage($request);

            foreach ($glossary as $en => $pl) {
                if (! str_contains($systemMessage, "{$en} → {$pl}")) {
                    return false;
                }
            }

            return true;
        });
    }

    public function test_estimate_context_adds_price_critical_note(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'translated']]],
            ], 200),
        ]);

        (new TranslationService)->translate('Brake pads', 'en', 'pl', 'estimate');

        Http::assertSent(function (Request $request): bool {
            $systemMessage = $this->extractSystemMessage($request);

            return str_contains($systemMessage, 'repair estimate line item');
        });
    }

    public function test_identical_call_is_served_from_cache_not_llm(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'klocki hamulcowe']]],
            ], 200),
        ]);

        $service = new TranslationService;

        $first = $service->translate('brake pads', 'en', 'pl', 'estimate');
        $second = $service->translate('brake pads', 'en', 'pl', 'estimate');

        $this->assertSame($first, $second);
        Http::assertSentCount(1);
    }

    private function extractSystemMessage(Request $request): string
    {
        $body = $request->data();
        foreach ($body['messages'] ?? [] as $message) {
            if (($message['role'] ?? null) === 'system') {
                return (string) ($message['content'] ?? '');
            }
        }

        return '';
    }
}
