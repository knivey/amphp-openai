<?php

namespace Knivey\OpenAi\Tests\Request;

use Knivey\OpenAi\Request\Content\AudioPart;
use Knivey\OpenAi\Request\Content\FilePart;
use Knivey\OpenAi\Request\Content\ImagePart;
use Knivey\OpenAi\Request\Content\TextPart;
use Knivey\OpenAi\Request\Message;
use PHPUnit\Framework\TestCase;

class MessageTest extends TestCase
{
    public function testDeveloperMessage(): void
    {
        $msg = Message::developer('you are helpful');
        $this->assertSame(['role' => 'developer', 'content' => 'you are helpful'], $msg->toArray());
    }

    public function testSystemMessage(): void
    {
        $msg = Message::system('you are helpful');
        $this->assertSame(['role' => 'system', 'content' => 'you are helpful'], $msg->toArray());
    }

    public function testUserMessageWithString(): void
    {
        $msg = Message::user('hello');
        $this->assertSame(['role' => 'user', 'content' => 'hello'], $msg->toArray());
    }

    public function testUserMessageWithContentParts(): void
    {
        $msg = Message::user([
            new TextPart('what is in this image?'),
            ImagePart::url('https://example.com/img.png'),
        ]);
        $arr = $msg->toArray();
        $this->assertSame('user', $arr['role']);
        $content = $arr['content'];
        $this->assertIsArray($content);
        $this->assertCount(2, $content);
        $part0 = $content[0];
        $this->assertIsArray($part0);
        $this->assertSame('text', $part0['type']);
        $part1 = $content[1];
        $this->assertIsArray($part1);
        $this->assertSame('image_url', $part1['type']);
    }

    public function testAssistantMessage(): void
    {
        $msg = Message::assistant('here is the answer');
        $this->assertSame(['role' => 'assistant', 'content' => 'here is the answer'], $msg->toArray());
    }

    public function testAssistantMessageWithNullContent(): void
    {
        $msg = Message::assistant(null);
        $this->assertSame(['role' => 'assistant', 'content' => null], $msg->toArray());
    }

    public function testAssistantMessageWithToolCallsAndRefusal(): void
    {
        $toolCalls = [
            ['id' => 'call_abc', 'type' => 'function', 'function' => ['name' => 'get_weather', 'arguments' => '{}']],
        ];
        $msg = Message::assistant(null, toolCalls: $toolCalls, refusal: 'I cannot help');
        $arr = $msg->toArray();
        $this->assertSame('assistant', $arr['role']);
        $this->assertNull($arr['content']);
        $this->assertSame($toolCalls, $arr['tool_calls']);
        $this->assertSame('I cannot help', $arr['refusal']);
    }

    public function testToolMessage(): void
    {
        $msg = Message::tool('result data', 'call_abc123');
        $arr = $msg->toArray();
        $this->assertSame('tool', $arr['role']);
        $this->assertSame('result data', $arr['content']);
        $this->assertSame('call_abc123', $arr['tool_call_id']);
    }

    public function testConstructorWithAllFields(): void
    {
        $msg = new Message(
            role: 'assistant',
            content: 'text',
            name: 'my_assistant',
            refusal: 'I cannot do that',
            toolCallId: 'call_123',
            functionCall: ['name' => 'get_weather', 'arguments' => '{}'],
            audio: ['id' => 'audio_123'],
            toolCalls: [],
        );
        $arr = $msg->toArray();
        $this->assertSame('assistant', $arr['role']);
        $this->assertSame('my_assistant', $arr['name']);
        $this->assertSame('I cannot do that', $arr['refusal']);
        $this->assertSame('call_123', $arr['tool_call_id']);
        $this->assertSame(['name' => 'get_weather', 'arguments' => '{}'], $arr['function_call']);
        $this->assertSame(['id' => 'audio_123'], $arr['audio']);
    }

    public function testConstructorOmitsNullFields(): void
    {
        $msg = new Message(role: 'user', content: 'hi');
        $arr = $msg->toArray();
        $this->assertArrayNotHasKey('name', $arr);
        $this->assertArrayNotHasKey('refusal', $arr);
        $this->assertArrayNotHasKey('tool_call_id', $arr);
        $this->assertArrayNotHasKey('function_call', $arr);
        $this->assertArrayNotHasKey('audio', $arr);
        $this->assertArrayNotHasKey('tool_calls', $arr);
    }

    public function testMultimodalUserMessage(): void
    {
        $msg = Message::user([
            new TextPart('describe these'),
            ImagePart::url('https://example.com/a.png'),
            ImagePart::base64('abc', 'image/png'),
            new AudioPart('dGVzdA==', 'mp3'),
            new FilePart(fileId: 'file-123'),
        ]);
        $arr = $msg->toArray();
        $content = $arr['content'];
        $this->assertIsArray($content);
        $this->assertCount(5, $content);
        $part0 = $content[0];
        $this->assertIsArray($part0);
        $this->assertSame('text', $part0['type']);
        $part1 = $content[1];
        $this->assertIsArray($part1);
        $this->assertSame('image_url', $part1['type']);
        $part2 = $content[2];
        $this->assertIsArray($part2);
        $this->assertSame('image_url', $part2['type']);
        $part3 = $content[3];
        $this->assertIsArray($part3);
        $this->assertSame('input_audio', $part3['type']);
        $part4 = $content[4];
        $this->assertIsArray($part4);
        $this->assertSame('file', $part4['type']);
    }
}
