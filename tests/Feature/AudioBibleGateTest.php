<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Engine;
use App\Models\Bible;

/**
 * The audio actions previously checked only that a Bible module existed,
 * skipping the installed/enabled gate applied to every other Bible selection.
 * Engine::isBibleEnabled() already existed but was called from nowhere.
 */
class AudioBibleGateTest extends TestCase
{
    /**
     * Find an installed Bible and temporarily disable it, restoring in a
     * finally. This is a real content row, so the change is reverted whatever
     * happens.
     */
    public function testDisabledBibleIsRejectedByAudio(): void
    {
        config(['audio.enable' => true]);

        $Bible = Bible::where('installed', 1)->where('enabled', 1)->first();
        $this->assertNotNull($Bible, 'Precondition: an enabled Bible is installed');

        $was_enabled = $Bible->enabled;

        try {
            $Bible->enabled = 0;
            $Bible->save();

            $Engine = new Engine();
            $result = $Engine->actionAudio([
                'bible'         => $Bible->module,
                'book'          => 'Genesis',
                'chapter_verse' => '1:1',
            ]);

            $this->assertTrue($Engine->hasErrors(), 'A disabled Bible must not be served audio');
        }
        finally {
            $Bible->enabled = $was_enabled;
            $Bible->save();
        }
    }

    /**
     * A module that does not exist is still rejected.
     */
    public function testUnknownModuleIsRejected(): void
    {
        config(['audio.enable' => true]);

        $Engine = new Engine();
        $Engine->actionAudio([
            'bible'         => 'no_such_bible_xyz',
            'book'          => 'Genesis',
            'chapter_verse' => '1:1',
        ]);

        $this->assertTrue($Engine->hasErrors());
    }

    /**
     * The bulk generation path is capped, so one request cannot drive an
     * unbounded number of external TTS calls.
     */
    public function testBulkGenerationCapIsConfigured(): void
    {
        $limit = (int) config('text_to_speech.max_verses_per_request', 200);

        $this->assertGreaterThan(0, $limit);

        $source = file_get_contents(app_path('AudioManager.php'));
        $this->assertStringContainsString('max_verses_per_request', $source);
    }

    /**
     * The cap used to be read from an 'audio.' key that nothing defined, so it
     * was pinned to its inline default and could not be tuned. Reading it with
     * a fallback (as the assertion above does) passes either way, so this
     * asserts the config file itself supplies the value.
     */
    public function testBulkGenerationCapIsDefinedInConfigNotJustDefaulted(): void
    {
        $config = require config_path('text_to_speech.php');

        $this->assertArrayHasKey('max_verses_per_request', $config);
        $this->assertIsInt($config['max_verses_per_request']);
        $this->assertGreaterThan(0, $config['max_verses_per_request']);

        $this->assertNull(
            config('audio.max_verses_per_request'),
            'The cap must no longer live in the audio.* namespace'
        );
    }
}
