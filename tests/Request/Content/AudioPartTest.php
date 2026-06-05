<?php

namespace Knivey\OpenAi\Tests\Request\Content;

use Knivey\OpenAi\Request\Content\AudioPart;
use PHPUnit\Framework\TestCase;

class AudioPartTest extends TestCase
{
    public function testToArrayReturnsAudioPart(): void
    {
        $part = new AudioPart('dGVzdA==', 'mp3');
        $this->assertSame(
            ['type' => 'input_audio', 'input_audio' => ['data' => 'dGVzdA==', 'format' => 'mp3']],
            $part->toArray(),
        );
    }

    public function testWavFormat(): void
    {
        $part = new AudioPart('dGVzdA==', 'wav');
        $arr = $part->toArray();
        $inputAudio = $arr['input_audio'];
        $this->assertIsArray($inputAudio);
        $this->assertSame('wav', $inputAudio['format']);
    }
}
