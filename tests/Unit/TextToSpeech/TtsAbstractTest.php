<?php

namespace Tests\Unit\TextToSpeech;

use PHPUnit\Framework\TestCase;
use App\TextToSpeech\TtsAbstract;
use App\Models\Bible;
use PHPUnit\Framework\Attributes\DataProvider;


/**
 * Unit tests for App\TextToSpeech\TtsAbstract
 */
class TtsAbstractTest extends TestCase
{
    // A small concrete implementation for testing the abstract class.
    protected function makeTtsInstance(Bible $bible = null, $file_extension = 'mp3')
    {
        $bible = $bible ?: new Bible();
        $that = $this;

        // create an anonymous subclass to implement abstract method and expose helpers for testing
        return new class($bible, $file_extension) extends TtsAbstract 
        {
            public function __construct($module, $file_extension = 'mp3')
            {
                // allow overriding file_extension for testing by setting property before parent ctor check
                $this->file_extension = $file_extension;
                parent::__construct($module);
            }

            protected function generateAudioHelper($text, $options, $file_handle)
            {
                // write the text for verification and return true
                fwrite($file_handle, $text);
                return true;
            }

            // expose protected formatter for testing
            public function callFormatText($text)
            {
                return $this->_formatText($text);
            }
        };
    }

    public function testGetAudioBasePath()
    {
        $path = TtsAbstract::getAudioBasePath();
        $this->assertIsString($path);
        $this->assertStringContainsString('bibles' . DIRECTORY_SEPARATOR . 'audio', $path);
    }

    #[DataProvider('formatTextProvider')]
    public function testFormatText($input, $expected)
    {
        $b = new Bible();
        $b->module = 'X';
        $tts = $this->makeTtsInstance($b);

        $out = $tts->callFormatText($input);
        $this->assertEquals($expected, $out);
    }

    public static function formatTextProvider()
    {
        return [
            ["<p>{123} faith [hope] ‹red› ¶ hello</p>", "faith hope red hello"],
            ["No markers here.", "No markers here."],
            ["{remove this} keep this", "keep this"],
            ["[italic] ‹red› {strongs}", "italic red"],
            ["¶ Paragraph marker at start.", "Paragraph marker at start."],
            [
                "In the beginning{H7225} God{H430} created{H1254}{(H8804)}{H853} the heaven{H8064} and{H853} the earth{H776}.",
                "In the beginning God created the heaven and the earth."
            ],
            [
                '¶ ‹Think not that I am come to destroy the law, or the prophets: I am not come to destroy, but to fulfil.›',
                'Think not that I am come to destroy the law, or the prophets: I am not come to destroy, but to fulfil.'
            ]
        ];
    }
}