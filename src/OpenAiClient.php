<?php

namespace Knivey\OpenAi;

use Amp\Http\Client\HttpClientBuilder;
use Amp\Pipeline\Pipeline;
use Knivey\OpenAi\Request\ChatRequest;
use Knivey\OpenAi\Response\ChatChunk;
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

    /**
     * @return Pipeline<ChatChunk>
     */
    public function chatCompletionStream(ChatRequest $request): Pipeline
    {
        $body = $request->toArray();
        $body['stream'] = true;

        return Pipeline::fromIterable(function () use ($body): \Generator {
            $stream = $this->getHttpClient()->postStream(
                $this->baseUrl . '/chat/completions',
                $body,
            );

            $buffer = '';
            while (null !== $chunk = $stream->read()) {
                $buffer .= $chunk;
                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 1);
                    $line = trim($line);
                    if ($line === '') {
                        continue;
                    }
                    if ($line === 'data: [DONE]') {
                        return;
                    }
                    if (str_starts_with($line, 'data: ')) {
                        $json = substr($line, 6);
                        /** @var array<string, mixed> $data */
                        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
                        yield ChatChunk::fromApiResponse($data);
                    }
                }
            }
        });
    }
}
