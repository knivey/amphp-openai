<?php

namespace Knivey\OpenAi;

use Amp\Http\Client\DelegateHttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request as HttpRequest;
use Knivey\OpenAi\Exception\ApiException;
use Knivey\OpenAi\Exception\AuthenticationException;
use Knivey\OpenAi\Exception\RateLimitException;

class HttpClient
{
    public function __construct(
        private readonly string $apiKey,
        private readonly DelegateHttpClient $client,
    ) {
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function post(string $uri, array $body): array
    {
        $request = new HttpRequest($uri, 'POST');
        $request->setHeader('Authorization', 'Bearer ' . $this->apiKey);
        $request->setHeader('Content-Type', 'application/json');
        $request->setBody(json_encode($body, JSON_THROW_ON_ERROR));

        $response = $this->client->request($request, new \Amp\NullCancellation());
        $responseBody = $response->getBody()->buffer();
        $status = $response->getStatus();

        if ($status === 429) {
            throw new RateLimitException('Rate limited', $status, $responseBody);
        }
        if ($status === 401 || $status === 403) {
            throw new AuthenticationException('Authentication failed', $status, $responseBody);
        }
        if ($status >= 400) {
            throw new ApiException("API error: HTTP {$status}", $status, $responseBody);
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /**
     * @param array<string, mixed> $body
     * @return \Amp\ByteStream\ReadableStream
     */
    public function postStream(string $uri, array $body): \Amp\ByteStream\ReadableStream
    {
        $request = new HttpRequest($uri, 'POST');
        $request->setHeader('Authorization', 'Bearer ' . $this->apiKey);
        $request->setHeader('Content-Type', 'application/json');
        $request->setBody(json_encode($body, JSON_THROW_ON_ERROR));

        $response = $this->client->request($request, new \Amp\NullCancellation());
        $status = $response->getStatus();

        if ($status === 429) {
            $responseBody = $response->getBody()->buffer();
            throw new RateLimitException('Rate limited', $status, $responseBody);
        }
        if ($status === 401 || $status === 403) {
            $responseBody = $response->getBody()->buffer();
            throw new AuthenticationException('Authentication failed', $status, $responseBody);
        }
        if ($status >= 400) {
            $responseBody = $response->getBody()->buffer();
            throw new ApiException("API error: HTTP {$status}", $status, $responseBody);
        }

        return $response->getBody();
    }

    public function getAmpClient(): DelegateHttpClient
    {
        return $this->client;
    }
}
