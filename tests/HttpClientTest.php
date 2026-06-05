<?php

namespace Knivey\OpenAi\Tests;

use Amp\ByteStream\Payload;
use Amp\Future;
use Amp\Http\Client\DelegateHttpClient;
use Amp\Http\Client\HttpClientBuilder;
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
        $mockClient = new class($response) implements DelegateHttpClient {
            public function __construct(private Response $response) {}

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
        $mockClient = new class($response) implements DelegateHttpClient {
            public function __construct(private Response $response) {}

            public function request(HttpRequest $request, \Amp\Cancellation $cancellation): Response
            {
                return $this->response;
            }
        };

        $client = new OpenAiHttpClient('bad-key', $mockClient);
        $client->post('/v1/chat/completions', []);
    }

    public function testThrowsRateLimitExceptionOn429(): void
    {
        $this->expectException(RateLimitException::class);
        $response = $this->createMockResponse(429, '{"error":"rate limited"}');
        $mockClient = new class($response) implements DelegateHttpClient {
            public function __construct(private Response $response) {}

            public function request(HttpRequest $request, \Amp\Cancellation $cancellation): Response
            {
                return $this->response;
            }
        };

        $client = new OpenAiHttpClient('test-key', $mockClient);
        $client->post('/v1/chat/completions', []);
    }

    public function testThrowsApiExceptionOn500(): void
    {
        $this->expectException(ApiException::class);
        $response = $this->createMockResponse(500, '{"error":"server error"}');
        $mockClient = new class($response) implements DelegateHttpClient {
            public function __construct(private Response $response) {}

            public function request(HttpRequest $request, \Amp\Cancellation $cancellation): Response
            {
                return $this->response;
            }
        };

        $client = new OpenAiHttpClient('test-key', $mockClient);
        $client->post('/v1/chat/completions', []);
    }
}
