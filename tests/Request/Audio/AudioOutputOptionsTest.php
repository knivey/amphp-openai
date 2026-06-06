<?php

namespace Knivey\OpenAi\Tests\Request\Audio;

use Knivey\OpenAi\Request\Audio\AudioOutputOptions;
use PHPUnit\Framework\TestCase;

class AudioOutputOptionsTest extends TestCase
{
    public function testStringVoiceToArray(): void
    {
        $opts = new AudioOutputOptions('alloy', 'mp3');
        $this->assertSame(['voice' => 'alloy', 'format' => 'mp3'], $opts->toArray());
    }

    public function testArrayVoiceToArray(): void
    {
        $opts = new AudioOutputOptions(['id' => 'custom-voice-123'], 'wav');
        $this->assertSame(['voice' => ['id' => 'custom-voice-123'], 'format' => 'wav'], $opts->toArray());
    }

    public function testDefaultFormat(): void
    {
        $opts = new AudioOutputOptions('echo');
        $this->assertSame('wav', $opts->format);
    }
}
