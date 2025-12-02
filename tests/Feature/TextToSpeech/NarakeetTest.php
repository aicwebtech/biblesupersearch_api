<?php

namespace Tests\Feature\TextToSpeech;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use App\TextToSpeech\Narakeet;
use App\Models\Language;
use PHPUnit\Framework\Attributes\DataProvider;

class NarakeetTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // Provide a predictable API key used by the service under test.
        config()->set('services.narakeet.key', 'test-api-key');
    }

    //#[DataProvider('languageDataProvider')]
    public function testVoiceByLanguage()
    {
        $languages = Language::select('languages.code', 'languages.name')
                    ->join('bibles', 'bibles.lang_short', 'languages.code')
                    ->groupBy('languages.id')->orderBy('languages.name');

        foreach($languages->get() as $lang) {
            $voice1 = Narakeet::getVoiceByLanguage($lang->code);
            $this->assertNotNull($voice1, 'No Narakeet voice for language: ' . $lang->name . ' : ' . $lang->code);
        }
    }

    public static function languageDataProvider()
    {
        // return [
        //     ['en'],
        //     ['es'],
        //     ['fr'],
        //     ['de'],
        //     ['it'],
        //     ['pt'],
        //     ['ru'],
        //     ['zh'],
        //     ['ja'],
        //     ['ko'],
        // ];
        
        
        $languages = Language::select('languages.code')
                    ->join('bibles', 'bibles.lang_short', 'languages.code')
                    ->groupBy('languages.id');

        $data = [];

        foreach($languages->get() as $lang) {
            $data[] = [$lang->code];
        }

        print_r($data);

        return $data;
    }

    public function __test_it_synthesizes_text_and_returns_download_url()
    {
        $expectedUrl = 'https://cdn.narakeet.com/audio/example.mp3';

        // Fake the Narakeet API response
        Http::fake([
            'https://api.narakeet.com/*' => Http::response([
                'downloadUrl' => $expectedUrl,
            ], 201),
        ]);

        $narakeet = new Narakeet('kjv');

        $result = $narakeet->generateAudio('Hello world');

        $this->assertIsString($result);
        $this->assertEquals($expectedUrl, $result);

        // Ensure request included Authorization header with configured key
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.narakeet.com')
                && $request->hasHeader('Authorization')
                && in_array('Bearer test-api-key', $request->header('Authorization'));
        });
    }

    public function __test_it_sends_voice_and_format_options_in_payload()
    {
        Http::fake([
            'https://api.narakeet.com/*' => Http::response([
                'downloadUrl' => 'https://cdn.narakeet.com/audio/example.wav',
            ], 201),
        ]);

        $narakeet = new Narakeet('kjv');

        $narakeet->generateAudio('Testing voice and format', [
            'voice' => 'Amy',
            'format' => 'wav',
        ]);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'api.narakeet.com')) {
                return false;
            }

            // request body is JSON; decode and inspect fields
            $body = json_decode($request->body(), true);
            return is_array($body)
                && isset($body['voice']) && $body['voice'] === 'Amy'
                && isset($body['format']) && $body['format'] === 'wav'
                && (isset($body['script']) || isset($body['text']));
        });
    }

    public function __test_it_throws_exception_on_error_response()
    {
        Http::fake([
            'https://api.narakeet.com/*' => Http::response([
                'error' => 'Invalid payload',
            ], 400),
        ]);

        $this->expectException(\RuntimeException::class);

        $narakeet = new Narakeet('kjv');

        $narakeet->generateAudio('This will fail');
    }
}