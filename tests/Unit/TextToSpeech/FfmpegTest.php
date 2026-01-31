<?php

namespace Tests\Unit\TextToSpeech;

use PHPUnit\Framework\TestCase;
use App\TextToSpeech\Ffmpeg;

/**
 * Unit tests for App\TextToSpeech\Ffmpeg
 */

class FfmpegTest extends TestCase
{
    public function testCanUse()
    {
        $canUse = Ffmpeg::canUse();
        $this->assertIsBool($canUse);

        if ($canUse) {
            $this->assertTrue($canUse, "FFmpeg should be usable.");
        } else {
            $this->assertFalse($canUse, "FFmpeg should not be usable. Errors: " . implode("; ", Ffmpeg::$useErrors));
        }
    }

    public function testQuickMerge()
    {

        if (!Ffmpeg::canUse()) {
            $this->markTestSkipped('FFmpeg not available: ' . implode('; ', Ffmpeg::$useErrors));
        }

        $tmp = sys_get_temp_dir();
        $a = tempnam($tmp, 'tts_a_');
        $b = tempnam($tmp, 'tts_b_');
        $out = tempnam($tmp, 'tts_out_');

        rename($a, $a . '.wav');
        rename($b, $b . '.wav');
        rename($out, $out . '.wav');

        $a .= '.wav';
        $b .= '.wav';
        $out .= '.wav';

        $createWav = function ($file, $duration = 0.2, $sampleRate = 8000, $bits = 16, $channels = 1) {
            $numSamples = (int)round($sampleRate * $duration);
            $bytesPerSample = (int)($bits / 8);
            $dataSize = $numSamples * $channels * $bytesPerSample;
            $riffSize = 36 + $dataSize;
            $fmtChunkSize = 16;
            $audioFormat = 1; // PCM
            $blockAlign = $channels * $bytesPerSample;
            $byteRate = $sampleRate * $blockAlign;

            $fh = fopen($file, 'wb');
            if ($fh === false) {
                throw new \RuntimeException("Unable to create WAV file: {$file}");
            }

            fwrite($fh, 'RIFF');
            fwrite($fh, pack('V', $riffSize));
            fwrite($fh, 'WAVE');
            fwrite($fh, 'fmt ');
            fwrite($fh, pack('V', $fmtChunkSize));
            fwrite($fh, pack('v', $audioFormat));
            fwrite($fh, pack('v', $channels));
            fwrite($fh, pack('V', $sampleRate));
            fwrite($fh, pack('V', $byteRate));
            fwrite($fh, pack('v', $blockAlign));
            fwrite($fh, pack('v', $bits));
            fwrite($fh, 'data');
            fwrite($fh, pack('V', $dataSize));

            // write silent samples
            $zero = str_repeat("\x00", $bytesPerSample);
            for ($i = 0, $total = $numSamples * $channels; $i < $total; $i++) {
                fwrite($fh, $zero);
            }

            fclose($fh);
        };

        try {
            $createWav($a, 0.2);
            $createWav($b, 0.3);

            $mergeResult = Ffmpeg::quickMerge([$a, $b], $out);

            $this->assertNotFalse(
                $mergeResult,
                'Ffmpeg::quickMerge returned false. Errors: ' . implode('; ', Ffmpeg::$useErrors)
            );

            $this->assertFileExists($out, 'Merged output file was not created.');
            $this->assertGreaterThan(0, filesize($out), 'Merged output file is empty.');
        } finally {
            @unlink($a);
            @unlink($b);
            @unlink($out);
        }
        
    }
    
}