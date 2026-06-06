<?php

namespace Knivey\OpenAi\Tests\Response;

use Knivey\OpenAi\Response\ChatResponse;
use PHPUnit\Framework\TestCase;

class ChatResponseTest extends TestCase
{
    private const FULL_RESPONSE = [
        'id' => 'chatcmpl-123',
        'object' => 'chat.completion',
        'created' => 1234567890,
        'model' => 'gpt-4',
        'choices' => [
            [
                'index' => 0,
                'message' => [
                    'role' => 'assistant',
                    'content' => 'Hello!',
                    'refusal' => null,
                ],
                'finish_reason' => 'stop',
                'logprobs' => null,
            ],
        ],
        'usage' => [
            'prompt_tokens' => 10,
            'completion_tokens' => 5,
            'total_tokens' => 15,
        ],
        'service_tier' => 'auto',
        'system_fingerprint' => 'fp_abc123',
    ];

    public function testFromApiResponse(): void
    {
        $resp = ChatResponse::fromApiResponse(self::FULL_RESPONSE);
        $this->assertSame('chatcmpl-123', $resp->id);
        $this->assertSame('chat.completion', $resp->object);
        $this->assertSame(1234567890, $resp->created);
        $this->assertSame('gpt-4', $resp->model);
        $this->assertCount(1, $resp->choices);
        $this->assertSame(0, $resp->choices[0]->index);
        $this->assertSame('Hello!', $resp->choices[0]->message->content);
        $this->assertSame('stop', $resp->choices[0]->finishReason);
        $this->assertNotNull($resp->usage);
        $this->assertSame(15, $resp->usage->totalTokens);
        $this->assertSame('auto', $resp->serviceTier);
        $this->assertSame('fp_abc123', $resp->systemFingerprint);
    }

    public function testResponseWithToolCalls(): void
    {
        $data = self::FULL_RESPONSE;
        $data['choices'][0]['message'] = [
            'role' => 'assistant',
            'content' => null,
            'tool_calls' => [
                ['id' => 'call_1', 'type' => 'function', 'function' => ['name' => 'get_weather', 'arguments' => '{}']],
            ],
        ];
        $data['choices'][0]['finish_reason'] = 'tool_calls';

        $resp = ChatResponse::fromApiResponse($data);
        $this->assertNull($resp->choices[0]->message->content);
        $this->assertNotNull($resp->choices[0]->message->toolCalls);
        $this->assertCount(1, $resp->choices[0]->message->toolCalls);
        $this->assertNotNull($resp->choices[0]->message->toolCalls[0]->function);
        $this->assertSame('get_weather', $resp->choices[0]->message->toolCalls[0]->function['name']);
        $this->assertSame('tool_calls', $resp->choices[0]->finishReason);
    }

    public function testResponseWithAnnotations(): void
    {
        $data = self::FULL_RESPONSE;
        $data['choices'][0]['message']['annotations'] = [
            [
                'type' => 'url_citation',
                'url_citation' => [
                    'start_index' => 0,
                    'end_index' => 10,
                    'title' => 'Test',
                    'url' => 'https://example.com',
                ],
            ],
        ];

        $resp = ChatResponse::fromApiResponse($data);
        $this->assertNotNull($resp->choices[0]->message->annotations);
        $this->assertCount(1, $resp->choices[0]->message->annotations);
        $this->assertNotNull($resp->choices[0]->message->annotations[0]->urlCitation);
        $this->assertSame('Test', $resp->choices[0]->message->annotations[0]->urlCitation->title);
    }

    public function testResponseWithNulls(): void
    {
        $data = self::FULL_RESPONSE;
        unset($data['usage'], $data['service_tier'], $data['system_fingerprint']);

        $resp = ChatResponse::fromApiResponse($data);
        $this->assertNull($resp->usage);
        $this->assertNull($resp->serviceTier);
        $this->assertNull($resp->systemFingerprint);
    }
}
