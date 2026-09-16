<?php

namespace Tests\Unit\Http\Controllers;

use PHPUnit\Framework\TestCase;
use App\Http\Controllers\Controller;

/**
 * Concrete subclass exposing the protected serializer under test.
 */
class BootstrapEncodingStub extends Controller
{
    public function encode($bootstrap): string
    {
        return $this->encodeBootstrap($bootstrap);
    }
}

/**
 * The admin views emit the bootstrap payload raw into a <script> block
 * (`var bootstrap = @php echo $bootstrap @endphp;`), so encodeBootstrap() must
 * neutralise markup characters in admin-supplied string content such as
 * copyright statements and language names.
 */
class EncodeBootstrapTest extends TestCase
{
    protected function encode($bootstrap): string
    {
        return (new BootstrapEncodingStub())->encode($bootstrap);
    }

    /**
     * Build the expected hex escape for a character without spelling the
     * \uXXXX sequence out as a literal.
     *
     * @param  string  $char
     * @param  int     $flag
     * @return string
     */
    protected function hexEscape(string $char, int $flag): string
    {
        return trim(json_encode($char, $flag), '"');
    }

    /**
     * No raw markup character may survive into the script block.
     */
    public function testMarkupCharactersAreNotEmittedRaw(): void
    {
        $json = $this->encode((object)['copyright' => '</script><script>alert(1)</script> & "x" & O\'Brien']);

        foreach(['<', '>', '&', "'"] as $char) {
            $this->assertStringNotContainsString($char, $json, 'Raw character leaked: ' . $char);
        }
    }

    public function testMarkupCharactersAreHexEscaped(): void
    {
        $json = $this->encode((object)['copyright' => '<b> & "x" & O\'Brien']);

        $this->assertStringContainsString($this->hexEscape('<', JSON_HEX_TAG), $json);
        $this->assertStringContainsString($this->hexEscape('>', JSON_HEX_TAG), $json);
        $this->assertStringContainsString($this->hexEscape('&', JSON_HEX_AMP), $json);
        $this->assertStringContainsString($this->hexEscape("'", JSON_HEX_APOS), $json);
        $this->assertStringContainsString($this->hexEscape('"', JSON_HEX_QUOT), $json);
    }

    /**
     * Malformed UTF-8 reaches this payload through third-party module metadata.
     * json_encode() returns FALSE for it, which against the string return type
     * raised a TypeError and 500'd every admin Bibles/Features/Languages page.
     */
    public function testInvalidUtf8DoesNotThrow(): void
    {
        $json = $this->encode((object)['copyright' => "Public Domain \xB1 1611"]);

        $this->assertJson($json, 'Invalid UTF-8 must still yield parseable JSON');
    }

    /**
     * Whatever cannot be encoded at all must still leave the page loadable.
     */
    public function testUnencodablePayloadFallsBackToAnEmptyObject(): void
    {
        $recursive = new \stdClass();
        $recursive->self = $recursive;

        $this->assertSame('{}', $this->encode($recursive));
    }

    /**
     * The escaping must be transport-only: the value a browser parses back out
     * has to be byte-identical to what went in.
     */
    public function testPayloadRoundTripsUnchanged(): void
    {
        $payload = (object)[
            'copyright' => '<b>Public Domain</b> & "friends" & O\'Brien',
            'nested'    => (object)['langs' => ['en', 'de']],
        ];

        $this->assertEquals($payload, json_decode($this->encode($payload)));
    }
}
