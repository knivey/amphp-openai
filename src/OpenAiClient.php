<?php

namespace Knivey\OpenAi;

use Amp\Http\Client\HttpClientBuilder;
use Amp\Pipeline\Pipeline;
use Knivey\OpenAi\Request\ChatRequest;
use Knivey\OpenAi\Response\ChatChunk;
use Knivey\OpenAi\Request\Tool\ToolRegistry;
use Knivey\OpenAi\Response\ChatResponse;

class OpenAiClient
{
    private readonly string $baseUrl;
    private readonly Provider $provider;

    public function __construct(
        private readonly string $apiKey,
        ?string $baseUrl = null,
        private readonly ?HttpClient $httpClient = null,
        ?Provider $provider = null,
    ) {
        $this->baseUrl = rtrim($baseUrl ?? 'https://api.openai.com/v1', '/');
        $this->provider = $provider ?? (
            str_contains($this->baseUrl, 'openrouter.ai')
                ? Provider::OPENROUTER
                : Provider::OPENAI
        );
    }

    public function getProvider(): Provider
    {
        return $this->provider;
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
        $body = $request->toArray($this->provider);
        $data = $this->getHttpClient()->post($this->baseUrl . '/chat/completions', $body);

        return ChatResponse::fromApiResponse($data);
    }

    public function chatCompletionWithTools(
        ChatRequest $request,
        ToolRegistry $registry,
        int $maxIterations = 10,
    ): ChatResponse {
        $messages = $request->messages;
        $iteration = 0;

        while (true) {
            $iteration++;
            if ($iteration > $maxIterations) {
                throw new \RuntimeException(
                    "Tool call loop exceeded max iterations ({$maxIterations}).",
                );
            }

            $currentRequest = $request->withMessages($messages);

            $response = $this->chatCompletion($currentRequest);

            $toolCalls = $response->choices[0]->message->toolCalls;
            if ($toolCalls === null || $toolCalls === []) {
                return $response;
            }

            $assistantMessage = $response->choices[0]->message;
            $messages[] = new \Knivey\OpenAi\Request\Message(
                role: 'assistant',
                content: $assistantMessage->content,
                toolCalls: array_map(static fn (\Knivey\OpenAi\Response\ToolCall $tc): array => [
                    'id' => $tc->id,
                    'type' => $tc->type,
                    'function' => $tc->function,
                ], $assistantMessage->toolCalls ?? []),
                refusal: $assistantMessage->refusal,
            );

            $results = $registry->dispatchAll(array_values($toolCalls));
            foreach ($toolCalls as $toolCall) {
                $result = $results[$toolCall->id];
                $content = is_string($result) ? $result : json_encode($result, JSON_THROW_ON_ERROR);
                $messages[] = \Knivey\OpenAi\Request\Message::tool(
                    $content,
                    $toolCall->id,
                );
            }
        }
    }

    /**
     * @return Pipeline<ChatChunk>
     */
    public function chatCompletionStream(ChatRequest $request): Pipeline
    {
        $body = $request->toArray($this->provider);
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

            if (trim($buffer) !== '') {
                $line = trim($buffer);
                if ($line !== 'data: [DONE]' && str_starts_with($line, 'data: ')) {
                    $json = substr($line, 6);
                    /** @var array<string, mixed> $data */
                    $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
                    yield ChatChunk::fromApiResponse($data);
                }
            }
        });
    }
}
