<?php

namespace Knivey\OpenAi\Tests\Request;

use Knivey\OpenAi\Request\Audio\AudioOutputOptions;
use Knivey\OpenAi\Request\ChatRequest;
use Knivey\OpenAi\Request\Content\ImagePart;
use Knivey\OpenAi\Request\Content\TextPart;
use Knivey\OpenAi\Request\Message;
use Knivey\OpenAi\Request\Tool\FunctionTool;
use Knivey\OpenAi\Response\StreamingOptions;
use PHPUnit\Framework\TestCase;

class ChatRequestTest extends TestCase
{
    public function testMinimalRequestToArray(): void
    {
        $req = new ChatRequest(
            model: 'gpt-4',
            messages: [Message::user('hello')],
        );
        $arr = $req->toArray();
        $this->assertSame('gpt-4', $arr['model']);
        $messages = $arr['messages'];
        $this->assertIsArray($messages);
        $this->assertCount(1, $messages);
        $this->assertArrayNotHasKey('temperature', $arr);
        $this->assertArrayNotHasKey('stream', $arr);
    }

    public function testFullRequestWithAllOptions(): void
    {
        $logitBias = ['log_123' => -100];
        $req = new ChatRequest(
            model: 'gpt-4',
            messages: [Message::user('hello')],
            temperature: 0.7,
            topP: 0.9,
            maxTokens: 100,
            maxCompletionTokens: 200,
            n: 2,
            stop: ['stop1', 'stop2'],
            stream: true,
            streamOptions: new StreamingOptions(includeUsage: true),
            frequencyPenalty: 0.5,
            presencePenalty: 0.3,
            seed: 42,
            logprobs: true,
            topLogprobs: 5,
            logitBias: $logitBias,
            user: 'user-123',
            store: true,
            metadata: ['key' => 'value'],
            serviceTier: 'auto',
            tools: [new FunctionTool('get_weather', description: 'Get weather')],
            toolChoice: 'auto',
            parallelToolCalls: true,
            responseFormat: ['type' => 'json_object'],
            modalities: ['text', 'audio'],
            audio: new AudioOutputOptions('alloy', 'mp3'),
            prediction: ['type' => 'content', 'content' => 'expected'],
            reasoningEffort: 'high',
            webSearch: ['enable' => true],
            moderation: ['type' => 'keyword'],
        );
        $arr = $req->toArray();
        $this->assertSame(0.7, $arr['temperature']);
        $this->assertSame(0.9, $arr['top_p']);
        $this->assertSame(100, $arr['max_tokens']);
        $this->assertSame(200, $arr['max_completion_tokens']);
        $this->assertSame(2, $arr['n']);
        $this->assertSame(['stop1', 'stop2'], $arr['stop']);
        $this->assertTrue($arr['stream']);
        $this->assertSame(['include_usage' => true], $arr['stream_options']);
        $this->assertSame(0.5, $arr['frequency_penalty']);
        $this->assertSame(0.3, $arr['presence_penalty']);
        $this->assertSame(42, $arr['seed']);
        $this->assertTrue($arr['logprobs']);
        $this->assertSame(5, $arr['top_logprobs']);
        $this->assertSame(['log_123' => -100], $arr['logit_bias']);
        $this->assertSame('user-123', $arr['user']);
        $this->assertTrue($arr['store']);
        $this->assertSame(['key' => 'value'], $arr['metadata']);
        $this->assertSame('auto', $arr['service_tier']);
        $tools = $arr['tools'];
        $this->assertIsArray($tools);
        $this->assertCount(1, $tools);
        $tool0 = $tools[0];
        $this->assertIsArray($tool0);
        $this->assertSame('function', $tool0['type']);
        $this->assertSame('auto', $arr['tool_choice']);
        $this->assertTrue($arr['parallel_tool_calls']);
        $this->assertSame(['type' => 'json_object'], $arr['response_format']);
        $this->assertSame(['text', 'audio'], $arr['modalities']);
        $this->assertSame(['voice' => 'alloy', 'format' => 'mp3'], $arr['audio']);
        $this->assertSame('high', $arr['reasoning_effort']);
        $webSearch = $arr['web_search'];
        $this->assertIsArray($webSearch);
        $this->assertTrue($webSearch['enable']);
        $moderation = $arr['moderation'];
        $this->assertIsArray($moderation);
        $this->assertSame('keyword', $moderation['type']);
    }

    public function testStopAsString(): void
    {
        $req = new ChatRequest(model: 'gpt-4', messages: [Message::user('hi')], stop: 'STOP');
        $this->assertSame('STOP', $req->toArray()['stop']);
    }

    public function testToolChoiceAsArray(): void
    {
        $req = new ChatRequest(
            model: 'gpt-4',
            messages: [Message::user('hi')],
            toolChoice: ['type' => 'function', 'function' => ['name' => 'get_weather']],
        );
        $this->assertSame(['type' => 'function', 'function' => ['name' => 'get_weather']], $req->toArray()['tool_choice']);
    }

    public function testOmitsNullFields(): void
    {
        $req = new ChatRequest(model: 'gpt-4', messages: []);
        $arr = $req->toArray();
        $this->assertCount(2, $arr);
        $this->assertArrayHasKey('model', $arr);
        $this->assertArrayHasKey('messages', $arr);
    }

    public function testFullRequestJsonSerialization(): void
    {
        $req = new ChatRequest(
            model: 'gpt-4o',
            messages: [
                Message::system('You are helpful'),
                Message::user([
                    new TextPart('What is in this image?'),
                    ImagePart::url('https://example.com/img.png', 'high'),
                ]),
            ],
            tools: [
                new FunctionTool(
                    'get_weather',
                    description: 'Get weather',
                    parameters: ['type' => 'object', 'properties' => ['location' => ['type' => 'string']]],
                    strict: true,
                ),
            ],
            toolChoice: 'auto',
            temperature: 0.7,
            maxTokens: 1000,
            streamOptions: new StreamingOptions(includeUsage: true),
        );

        $json = json_encode($req->toArray(), JSON_THROW_ON_ERROR);
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);

        $this->assertSame('gpt-4o', $decoded['model']);

        $messages = $decoded['messages'];
        $this->assertIsArray($messages);
        $msg0 = $messages[0];
        $this->assertIsArray($msg0);
        $this->assertSame('system', $msg0['role']);
        $msg1 = $messages[1];
        $this->assertIsArray($msg1);
        $this->assertSame('user', $msg1['role']);

        $userContent = $msg1['content'];
        $this->assertIsArray($userContent);
        $imgPart = $userContent[1];
        $this->assertIsArray($imgPart);
        $this->assertSame('image_url', $imgPart['type']);

        $tools = $decoded['tools'];
        $this->assertIsArray($tools);
        $tool0 = $tools[0];
        $this->assertIsArray($tool0);
        $this->assertSame('function', $tool0['type']);
        $toolFunction = $tool0['function'];
        $this->assertIsArray($toolFunction);
        $this->assertTrue($toolFunction['strict']);

        $this->assertSame(['include_usage' => true], $decoded['stream_options']);
    }
}
