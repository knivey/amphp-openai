<?php

namespace Knivey\OpenAi;

use Amp\Cancellation;
use Amp\Http\Client\DelegateHttpClient;
use Amp\Http\Client\Request as HttpRequest;
use Knivey\OpenAi\Exception\ApiException;
use Knivey\OpenAi\Exception\AuthenticationException;
use Knivey\OpenAi\Exception\RateLimitException;

class HttpClient
{
    private const MAX_RETRIES = 3;

    public function __construct(
        private readonly string $apiKey,
        private readonly DelegateHttpClient $client,
        private readonly ?Cancellation $cancellation = null,
    ) {
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function post(string $uri, array $body, ?Cancellation $cancellation = null): array
    {
        $response = $this->sendRequestWithRetry($uri, $body, $cancellation);
        $responseBody = $response->getBody()->buffer();
        $this->assertErrorStatus($response->getStatus(), $responseBody);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /**
     * @param array<string, mixed> $body
     * @return \Amp\ByteStream\ReadableStream
     */
    public function postStream(string $uri, array $body, ?Cancellation $cancellation = null): \Amp\ByteStream\ReadableStream
    {
        $response = $this->sendRequestWithRetry($uri, $body, $cancellation);
        $status = $response->getStatus();

        if ($status >= 400) {
            $responseBody = $response->getBody()->buffer();
            $this->assertErrorStatus($status, $responseBody);
        }

        return $response->getBody();
    }

    public function getAmpClient(): DelegateHttpClient
    {
        return $this->client;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function sendRequestWithRetry(string $uri, array $body, ?Cancellation $cancellation = null): \Amp\Http\Client\Response
    {
        $effectiveCancellation = $cancellation ?? $this->cancellation ?? new \Amp\NullCancellation();
        $attempts = 0;

        while (true) {
            $response = $this->sendRequest($uri, $body, $effectiveCancellation);

            if ($response->getStatus() === 429) {
                $attempts++;
                if ($attempts >= self::MAX_RETRIES) {
                    $responseBody = $response->getBody()->buffer();
                    throw new RateLimitException('Rate limited', 429, $responseBody);
                }

                $retryAfter = $this->parseRetryAfter($response);
                \Amp\delay($retryAfter, true, $effectiveCancellation);
                continue;
            }

            return $response;
        }
    }

    private function parseRetryAfter(\Amp\Http\Client\Response $response): int
    {
        $header = $response->getHeader('retry-after');
        if ($header !== null && ctype_digit($header)) {
            return (int) $header;
        }

        return 1;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function sendRequest(string $uri, array $body, Cancellation $cancellation): \Amp\Http\Client\Response
    {
        $request = new HttpRequest($uri, 'POST');
        $request->setHeader('Authorization', 'Bearer ' . $this->apiKey);
        $request->setHeader('Content-Type', 'application/json');
        $request->setBody(json_encode($body, JSON_THROW_ON_ERROR));

        return $this->client->request($request, $cancellation);
    }

    private function assertErrorStatus(int $status, string $responseBody): void
    {
        if ($status === 429) {
            throw new RateLimitException('Rate limited', $status, $responseBody);
        }
        if ($status === 401 || $status === 403) {
            throw new AuthenticationException('Authentication failed', $status, $responseBody);
        }
        if ($status >= 400) {
            throw new ApiException("API error: HTTP {$status}", $status, $responseBody);
        }
    }
}
