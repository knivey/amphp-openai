<?php

namespace Knivey\OpenAi\Tests;

use Amp\Http\Client\DelegateHttpClient;
use Amp\Http\Client\Request as HttpRequest;
use Amp\Http\Client\Response;
use Knivey\OpenAi\OpenAiClient;
use Knivey\OpenAi\Request\ChatRequest;
use Knivey\OpenAi\Request\Message;
use Knivey\OpenAi\Request\Tool\ReflectionTool;
use Knivey\OpenAi\Request\Tool\ToolRegistry;
use PHPUnit\Framework\TestCase;

class OpenAiClientTest extends TestCase
{
    private const SUCCESS_RESPONSE = [
        'id' => 'chatcmpl-123',
        'object' => 'chat.completion',
        'created' => 1234567890,
        'model' => 'gpt-4',
        'choices' => [
            [
                'index' => 0,
                'message' => [
                    'role' => 'assistant',
                    'content' => 'Hello! How can I help?',
                ],
                'finish_reason' => 'stop',
            ],
        ],
        'usage' => [
            'prompt_tokens' => 10,
            'completion_tokens' => 5,
            'total_tokens' => 15,
        ],
    ];

    private function createMockClient(string $body, int $status = 200): DelegateHttpClient
    {
        return new class ($status, $body) implements DelegateHttpClient {
            public function __construct(
                private int $status,
                private string $body,
            ) {
            }

            public function request(HttpRequest $request, \Amp\Cancellation $cancellation): Response
            {
                $dummyRequest = new HttpRequest('https://api.openai.com/v1/test');

                return new Response(
                    '1.1',
                    $this->status,
                    '',
                    [],
                    $this->body,
                    $dummyRequest,
                );
            }
        };
    }

    public function testChatCompletionReturnsResponse(): void
    {
        $mock = $this->createMockClient((string) json_encode(self::SUCCESS_RESPONSE));
        $client = new OpenAiClient('test-key', httpClient: new \Knivey\OpenAi\HttpClient('test-key', $mock));
        $request = new ChatRequest(model: 'gpt-4', messages: [Message::user('hello')]);

        $response = $client->chatCompletion($request);
        $this->assertSame('chatcmpl-123', $response->id);
        $this->assertSame('Hello! How can I help?', $response->choices[0]->message->content);
        $this->assertNotNull($response->usage);
        $this->assertSame(15, $response->usage->totalTokens);
    }

    public function testChatCompletionWithCustomBaseUrl(): void
    {
        $capturedUriHolder = new \stdClass();
        $capturedUriHolder->value = '';
        $mock = new class (self::SUCCESS_RESPONSE, $capturedUriHolder) implements DelegateHttpClient {
            private \stdClass $capturedUriHolder;

            /**
             * @param array<string, mixed> $responseData
             */
            public function __construct(
                private array $responseData,
                \stdClass $capturedUriHolder,
            ) {
                $this->capturedUriHolder = $capturedUriHolder;
            }

            public function request(HttpRequest $request, \Amp\Cancellation $cancellation): Response
            {
                $this->capturedUriHolder->value = (string) $request->getUri();
                $body = (string) json_encode($this->responseData);
                $dummyRequest = new HttpRequest('https://api.openai.com/v1/test');

                return new Response(
                    '1.1',
                    200,
                    '',
                    [],
                    $body,
                    $dummyRequest,
                );
            }
        };

        $client = new OpenAiClient(
            'test-key',
            baseUrl: 'https://custom.api.com/v1',
            httpClient: new \Knivey\OpenAi\HttpClient('test-key', $mock),
        );
        $request = new ChatRequest(model: 'gpt-4', messages: [Message::user('hello')]);
        $client->chatCompletion($request);

        $this->assertSame('https://custom.api.com/v1/chat/completions', $capturedUriHolder->value);
    }

    public function testChatCompletionStreamYieldsChunks(): void
    {
        $sseData = "data: " . json_encode([
            'id' => 'chatcmpl-123',
            'object' => 'chat.completion.chunk',
            'created' => 1234567890,
            'model' => 'gpt-4',
            'choices' => [
                ['index' => 0, 'delta' => ['content' => 'Hello'], 'finish_reason' => null],
            ],
        ]) . "\n\ndata: " . json_encode([
            'id' => 'chatcmpl-123',
            'object' => 'chat.completion.chunk',
            'created' => 1234567890,
            'model' => 'gpt-4',
            'choices' => [
                ['index' => 0, 'delta' => ['content' => ' world'], 'finish_reason' => 'stop'],
            ],
        ]) . "\n\ndata: [DONE]\n\n";

        $mock = $this->createMockClient($sseData);

        $client = new OpenAiClient('test-key', httpClient: new \Knivey\OpenAi\HttpClient('test-key', $mock));
        $request = new ChatRequest(model: 'gpt-4', messages: [Message::user('hello')]);

        $chunks = [];
        $pipeline = $client->chatCompletionStream($request);
        foreach ($pipeline as $chunk) {
            $chunks[] = $chunk;
        }

        $this->assertCount(2, $chunks);
        $this->assertSame('Hello', $chunks[0]->choices[0]->delta['content']);
        $this->assertSame(' world', $chunks[1]->choices[0]->delta['content']);
        $this->assertSame('stop', $chunks[1]->choices[0]->finishReason);
    }

    public function testChatCompletionWithToolsRunsFullLoop(): void
    {
        $callCount = 0;
        $toolCallResponse = [
            'id' => 'chatcmpl-1',
            'object' => 'chat.completion',
            'created' => 1234567890,
            'model' => 'gpt-4',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [
                            [
                                'id' => 'call_1',
                                'type' => 'function',
                                'function' => ['name' => 'echo', 'arguments' => '{"input":"hello"}'],
                            ],
                        ],
                    ],
                    'finish_reason' => 'tool_calls',
                ],
            ],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
        ];
        $finalResponse = [
            'id' => 'chatcmpl-2',
            'object' => 'chat.completion',
            'created' => 1234567890,
            'model' => 'gpt-4',
            'choices' => [
                [
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => 'The echo result is: result:hello'],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => ['prompt_tokens' => 20, 'completion_tokens' => 10, 'total_tokens' => 30],
        ];

        $mock = new class ($callCount, $toolCallResponse, $finalResponse) implements DelegateHttpClient {
            public int $callCount = 0;

            /** @param array<string, mixed> $toolCallResponse */
            public function __construct(
                int $callCount,
                private readonly array $toolCallResponse,
                /** @var array<string, mixed> */
                private readonly array $finalResponse,
            ) {
                $this->callCount = $callCount;
            }

            public function request(HttpRequest $request, \Amp\Cancellation $cancellation): Response
            {
                $this->callCount++;
                $data = $this->callCount === 1
                    ? $this->toolCallResponse
                    : $this->finalResponse;
                $body = (string) json_encode($data);
                $dummyRequest = new HttpRequest('https://api.openai.com/v1/test');

                return new Response('1.1', 200, '', [], $body, $dummyRequest);
            }
        };

        $client = new OpenAiClient('test-key', httpClient: new \Knivey\OpenAi\HttpClient('test-key', $mock));

        $registry = ToolRegistry::create()
            ->add(ReflectionTool::fromCallable(
                fn (string $input): string => "result:{$input}",
                name: 'echo',
            ));

        $response = $client->chatCompletionWithTools(
            new ChatRequest(model: 'gpt-4', messages: [Message::user('test')], tools: $registry->getTools()),
            $registry,
        );

        $this->assertSame('The echo result is: result:hello', $response->choices[0]->message->content);
        $this->assertSame(2, $mock->callCount);
    }

    public function testChatCompletionWithToolsMaxIterationsThrows(): void
    {
        $infiniteToolCallResponse = [
            'id' => 'chatcmpl-1',
            'object' => 'chat.completion',
            'created' => 1234567890,
            'model' => 'gpt-4',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [
                            [
                                'id' => 'call_loop',
                                'type' => 'function',
                                'function' => ['name' => 'echo', 'arguments' => '{"input":"loop"}'],
                            ],
                        ],
                    ],
                    'finish_reason' => 'tool_calls',
                ],
            ],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
        ];

        $mock = new class ($infiniteToolCallResponse) implements DelegateHttpClient {
            /** @param array<string, mixed> $response */
            public function __construct(private readonly array $response)
            {
            }

            public function request(HttpRequest $request, \Amp\Cancellation $cancellation): Response
            {
                $body = (string) json_encode($this->response);
                $dummyRequest = new HttpRequest('https://api.openai.com/v1/test');

                return new Response('1.1', 200, '', [], $body, $dummyRequest);
            }
        };

        $client = new OpenAiClient('test-key', httpClient: new \Knivey\OpenAi\HttpClient('test-key', $mock));

        $registry = ToolRegistry::create()
            ->add(ReflectionTool::fromCallable(
                fn (string $input): string => "result:{$input}",
                name: 'echo',
            ));

        $this->expectException(\RuntimeException::class);
        $client->chatCompletionWithTools(
            new ChatRequest(model: 'gpt-4', messages: [Message::user('test')], tools: $registry->getTools()),
            $registry,
            maxIterations: 2,
        );
    }
}
