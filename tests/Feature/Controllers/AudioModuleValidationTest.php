<?php

namespace Tests\Feature\Controllers;

use Tests\TestCase;
use App\User;
use App\TextToSpeech\TtsAbstract;

/**
 * upload() and scan() passed the raw `module` request value straight into an
 * audio directory path, where Symfony's File::move() would mkdir() a traversed
 * location. delete() already validated; these two did not.
 */
class AudioModuleValidationTest extends TestCase
{
    protected function admin(): User
    {
        $User = User::find(1);
        $this->assertGreaterThanOrEqual(100, $User->access_level);

        return $User;
    }

    public function testScanRejectsTraversingModule(): void
    {
        $response = $this->actingAs($this->admin())
                         ->postJson('/admin/bibles/audio/scan', ['module' => '../../evil']);

        $this->assertContains($response->getStatusCode(), [400, 404], 'Traversing module must be refused');
        $this->assertNotSame(200, $response->getStatusCode());
    }

    public function testScanRejectsUnknownModule(): void
    {
        $response = $this->actingAs($this->admin())
                         ->postJson('/admin/bibles/audio/scan', ['module' => 'no_such_bible_xyz']);

        $this->assertContains($response->getStatusCode(), [400, 404]);
    }

    /**
     * The path builder itself refuses too, so a caller that skips the
     * controller check still cannot escape the audio directory.
     */
    public function testAudioPathBuilderRejectsTraversingModule(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        TtsAbstract::getAudioFilePathStatic('../../evil');
    }

    public function testAudioPathBuilderAcceptsValidModule(): void
    {
        $path = TtsAbstract::getAudioFilePathStatic('kjv');

        // The base path is built with dirname(__FILE__) . '/../../bibles/audio/',
        // so it legitimately contains '..'; assert on the resolved location.
        $this->assertStringEndsWith('kjv', $path);
        $this->assertStringContainsString('bibles' . DIRECTORY_SEPARATOR . 'audio', $path);
    }
}
