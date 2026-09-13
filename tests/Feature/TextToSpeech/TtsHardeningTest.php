<?php

namespace Tests\Feature\TextToSpeech;

use Tests\TestCase;
use App\TextToSpeech\Narakeet;
use App\TextToSpeech\OpenAI;

/**
 * A stalled TTS provider used to pin a PHP-FPM worker indefinitely: no
 * CURLOPT_TIMEOUT or CURLOPT_CONNECTTIMEOUT was set anywhere in app/.
 * Write failures also disclosed absolute server paths to API clients.
 */
class TtsHardeningTest extends TestCase
{
    /**
     * @return array<int, array<int, string>>
     */
    public static function providerSourceProvider(): array
    {
        return [
            'narakeet' => [Narakeet::class],
            'openai'   => [OpenAI::class],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerSourceProvider')]
    public function testProviderSetsBothCurlTimeouts(string $class): void
    {
        $file = (new \ReflectionClass($class))->getFileName();
        $source = file_get_contents($file);

        $this->assertStringContainsString('CURLOPT_CONNECTTIMEOUT', $source, $class . ' must set a connect timeout');
        $this->assertStringContainsString('CURLOPT_TIMEOUT', $source, $class . ' must set a request timeout');
    }

    public function testTimeoutDefaultsAreSane(): void
    {
        $connect = (int) config('audio.tts_connect_timeout', 10);
        $total = (int) config('audio.tts_timeout', 120);

        $this->assertGreaterThan(0, $connect);
        $this->assertGreaterThan(0, $total);
        $this->assertGreaterThanOrEqual($connect, $total);
    }

    /**
     * The client-facing error must not carry the server's filesystem path.
     */
    public function testWriteFailureDoesNotDiscloseServerPath(): void
    {
        $source = file_get_contents(app_path('TextToSpeech/TtsAbstract.php'));

        $this->assertStringNotContainsString(
            "addError('Unable to open file for writing: ' . \$file_path)",
            $source,
            'The absolute path must not be returned to the client'
        );
        $this->assertStringContainsString("addError('Unable to generate audio')", $source);
    }
}
