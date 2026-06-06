<?php

namespace Knivey\OpenAi\Tests;

use Amp\Http\Client\DelegateHttpClient;
use Amp\Http\Client\Request as HttpRequest;
use Amp\Http\Client\Response;
use Knivey\OpenAi\Exception\AuthenticationException;
use Knivey\OpenAi\Exception\RateLimitException;
use Knivey\OpenAi\Exception\ApiException;
use Knivey\OpenAi\HttpClient as OpenAiHttpClient;
use PHPUnit\Framework\TestCase;

class HttpClientTest extends TestCase
{
    /**
     * @param array<non-empty-string, array<string>|string> $headers
     */
    private function createMockResponse(int $status, string $body, array $headers = []): Response
    {
        $dummyRequest = new HttpRequest('https://api.openai.com/v1/test');

        return new Response(
            '1.1',
            $status,
            '',
            $headers,
            $body,
            $dummyRequest,
        );
    }

    public function testSuccessfulPostReturnsJson(): void
    {
        $response = $this->createMockResponse(200, '{"result":"ok"}');
        $mockClient = new class ($response) implements DelegateHttpClient {
            public function __construct(private Response $response)
            {
            }

            public function request(HttpRequest $request, \Amp\Cancellation $cancellation): Response
            {
                return $this->response;
            }
        };

        $client = new OpenAiHttpClient('test-key', $mockClient);
        $result = $client->post('/v1/chat/completions', ['model' => 'gpt-4']);
        $this->assertSame(['result' => 'ok'], $result);
    }

    public function testThrowsAuthenticationExceptionOn401(): void
    {
        $this->expectException(AuthenticationException::class);
        $response = $this->createMockResponse(401, '{"error":"unauthorized"}');
        $mockClient = new class ($response) implements DelegateHttpClient {
            public function __construct(private Response $response)
            {
            }

            public function request(HttpRequest $request, \Amp\Cancellation $cancellation): Response
            {
                return $this->response;
            }
        };

        $client = new OpenAiHttpClient('bad-key', $mockClient);
        $client->post('/v1/chat/completions', []);
    }

    public function testThrowsRateLimitExceptionAfterMaxRetriesOn429(): void
    {
        $this->expectException(RateLimitException::class);
        $response = $this->createMockResponse(429, '{"error":"rate limited"}');
        $mockClient = new class ($response) implements DelegateHttpClient {
            public function __construct(private Response $response)
            {
            }

            public function request(HttpRequest $request, \Amp\Cancellation $cancellation): Response
            {
                return $this->response;
            }
        };

        $client = new OpenAiHttpClient('test-key', $mockClient);
        $client->post('/v1/chat/completions', []);
    }

    public function testRetries429AndSucceeds(): void
    {
        $response429 = $this->createMockResponse(429, '{"error":"rate limited"}');
        $response429Second = $this->createMockResponse(429, '{"error":"rate limited"}');
        $response200 = $this->createMockResponse(200, '{"result":"ok"}');
        $callCountHolder = new \stdClass();
        $callCountHolder->count = 0;
        $mockClient = new class ($response429, $response429Second, $response200, $callCountHolder) implements DelegateHttpClient {
            private int $localCallCount = 0;

            public function __construct(
                private Response $first,
                private Response $second,
                private Response $third,
                private \stdClass $counter,
            ) {
            }

            public function request(HttpRequest $request, \Amp\Cancellation $cancellation): Response
            {
                $this->localCallCount++;
                $this->counter->count = $this->localCallCount;
                if ($this->localCallCount === 1) {
                    return $this->first;
                }
                if ($this->localCallCount === 2) {
                    return $this->second;
                }
                return $this->third;
            }
        };

        $client = new OpenAiHttpClient('test-key', $mockClient);
        $result = $client->post('/v1/chat/completions', []);
        $this->assertSame(['result' => 'ok'], $result);
        $this->assertSame(3, $callCountHolder->count);
    }

    public function testThrowsApiExceptionOn500(): void
    {
        $this->expectException(ApiException::class);
        $response = $this->createMockResponse(500, '{"error":"server error"}');
        $mockClient = new class ($response) implements DelegateHttpClient {
            public function __construct(private Response $response)
            {
            }

            public function request(HttpRequest $request, \Amp\Cancellation $cancellation): Response
            {
                return $this->response;
            }
        };

        $client = new OpenAiHttpClient('test-key', $mockClient);
        $client->post('/v1/chat/completions', []);
    }

    public function testPostStreamThrowsRateLimitExceptionAfterMaxRetriesOn429(): void
    {
        $this->expectException(RateLimitException::class);
        $response = $this->createMockResponse(429, '{"error":"rate limited"}');
        $mockClient = new class ($response) implements DelegateHttpClient {
            public function __construct(private Response $response)
            {
            }

            public function request(HttpRequest $request, \Amp\Cancellation $cancellation): Response
            {
                return $this->response;
            }
        };

        $client = new OpenAiHttpClient('test-key', $mockClient);
        $client->postStream('/v1/chat/completions', []);
    }

    public function testPostStreamThrowsAuthenticationExceptionOn401(): void
    {
        $this->expectException(AuthenticationException::class);
        $response = $this->createMockResponse(401, '{"error":"unauthorized"}');
        $mockClient = new class ($response) implements DelegateHttpClient {
            public function __construct(private Response $response)
            {
            }

            public function request(HttpRequest $request, \Amp\Cancellation $cancellation): Response
            {
                return $this->response;
            }
        };

        $client = new OpenAiHttpClient('bad-key', $mockClient);
        $client->postStream('/v1/chat/completions', []);
    }

    public function testPostStreamThrowsApiExceptionOn500(): void
    {
        $this->expectException(ApiException::class);
        $response = $this->createMockResponse(500, '{"error":"server error"}');
        $mockClient = new class ($response) implements DelegateHttpClient {
            public function __construct(private Response $response)
            {
            }

            public function request(HttpRequest $request, \Amp\Cancellation $cancellation): Response
            {
                return $this->response;
            }
        };

        $client = new OpenAiHttpClient('test-key', $mockClient);
        $client->postStream('/v1/chat/completions', []);
    }
}
