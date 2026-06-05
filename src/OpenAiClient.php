<?php

namespace Knivey\OpenAi;

use Amp\Http\Client\HttpClientBuilder;
use Knivey\OpenAi\Request\ChatRequest;
use Knivey\OpenAi\Response\ChatResponse;

class OpenAiClient
{
    private readonly string $baseUrl;

    public function __construct(
        private readonly string $apiKey,
        ?string $baseUrl = null,
        private readonly ?HttpClient $httpClient = null,
    ) {
        $this->baseUrl = rtrim($baseUrl ?? 'https://api.openai.com/v1', '/');
    }

    private function getHttpClient(): HttpClient
    {
        return $this->httpClient ?? new HttpClient(
            $this->apiKey,
            HttpClientBuilder::buildDefault(),
        );
    }

    public function chatCompletion(ChatRequest $request): ChatResponse
    {
        $body = $request->toArray();
        $data = $this->getHttpClient()->post($this->baseUrl . '/chat/completions', $body);

        return ChatResponse::fromApiResponse($data);
    }
}
