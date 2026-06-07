<?php

declare(strict_types=1);

namespace Tests\Feature\Translation;

use App\Services\TranslationService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class TranslationRequiredTest extends TestCase
{
    public function test_same_locale_pl_pl_does_not_translate(): void
    {
        Http::fake();
        $service = new TranslationService;

        $this->assertFalse($service->needsTranslation('pl', 'pl'));

        $result = $service->translate('klocki hamulcowe wymienione', 'pl', 'pl');

        $this->assertSame('klocki hamulcowe wymienione', $result);
        Http::assertNothingSent();
    }

    public function test_same_locale_en_en_does_not_translate(): void
    {
        Http::fake();
        $service = new TranslationService;

        $this->assertFalse($service->needsTranslation('en', 'en'));

        $result = $service->translate('brake pads replaced', 'en', 'en');

        $this->assertSame('brake pads replaced', $result);
        Http::assertNothingSent();
    }

    public function test_pl_to_en_pair_calls_translator(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'brake pads replaced']]],
            ], 200),
        ]);
        $service = new TranslationService;

        $this->assertTrue($service->needsTranslation('pl', 'en'));

        $result = $service->translate('klocki hamulcowe wymienione', 'pl', 'en');

        $this->assertSame('brake pads replaced', $result);
        Http::assertSentCount(1);
    }

    public function test_en_to_pl_pair_calls_translator(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'klocki hamulcowe wymienione']]],
            ], 200),
        ]);
        $service = new TranslationService;

        $this->assertTrue($service->needsTranslation('en', 'pl'));

        $result = $service->translate('brake pads replaced', 'en', 'pl');

        $this->assertSame('klocki hamulcowe wymienione', $result);
        Http::assertSentCount(1);
    }
}
