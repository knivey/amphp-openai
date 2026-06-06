<?php

namespace Knivey\OpenAi\Request;

use Knivey\OpenAi\Request\Audio\AudioOutputOptions;
use Knivey\OpenAi\Request\Tool\ToolDefinition;
use Knivey\OpenAi\Request\StreamingOptions;

readonly class ChatRequest
{
    /**
     * @param array<int, Message> $messages
     * @param string|array<int, string>|null $stop
     * @param array<int, ToolDefinition>|null $tools
     * @param string|array<string, mixed>|null $toolChoice
     * @param array<string, mixed>|null $responseFormat
     * @param array<int, string>|null $modalities
     * @param array<string, mixed>|null $prediction
     * @param array<string, mixed>|null $webSearch
     * @param array<string, mixed>|null $moderation
     * @param array<string, mixed>|null $metadata
     * @param array<string, int>|null $logitBias
     */
    public function __construct(
        public string $model,
        public array $messages,
        public ?float $temperature = null,
        public ?float $topP = null,
        public ?int $maxTokens = null,
        public ?int $maxCompletionTokens = null,
        public ?int $n = null,
        public string|array|null $stop = null,
        public ?bool $stream = null,
        public ?StreamingOptions $streamOptions = null,
        public ?float $frequencyPenalty = null,
        public ?float $presencePenalty = null,
        public ?int $seed = null,
        public ?bool $logprobs = null,
        public ?int $topLogprobs = null,
        public ?array $logitBias = null,
        public ?string $user = null,
        public ?bool $store = null,
        public ?array $metadata = null,
        public ?string $serviceTier = null,
        public ?array $tools = null,
        public string|array|null $toolChoice = null,
        public ?bool $parallelToolCalls = null,
        public ?array $responseFormat = null,
        public ?array $modalities = null,
        public ?AudioOutputOptions $audio = null,
        public ?array $prediction = null,
        public ?string $reasoningEffort = null,
        public ?array $webSearch = null,
        public ?array $moderation = null,
    ) {
    }

    /**
     * @param array<int, Message> $messages
     */
    public function withMessages(array $messages): self
    {
        return new self(
            model: $this->model,
            messages: $messages,
            temperature: $this->temperature,
            topP: $this->topP,
            maxTokens: $this->maxTokens,
            maxCompletionTokens: $this->maxCompletionTokens,
            n: $this->n,
            stop: $this->stop,
            stream: $this->stream,
            streamOptions: $this->streamOptions,
            frequencyPenalty: $this->frequencyPenalty,
            presencePenalty: $this->presencePenalty,
            seed: $this->seed,
            logprobs: $this->logprobs,
            topLogprobs: $this->topLogprobs,
            logitBias: $this->logitBias,
            user: $this->user,
            store: $this->store,
            metadata: $this->metadata,
            serviceTier: $this->serviceTier,
            tools: $this->tools,
            toolChoice: $this->toolChoice,
            parallelToolCalls: $this->parallelToolCalls,
            responseFormat: $this->responseFormat,
            modalities: $this->modalities,
            audio: $this->audio,
            prediction: $this->prediction,
            reasoningEffort: $this->reasoningEffort,
            webSearch: $this->webSearch,
            moderation: $this->moderation,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'model' => $this->model,
            'messages' => array_map(static fn (Message $m): array => $m->toArray(), $this->messages),
        ];

        if ($this->temperature !== null) {
            $result['temperature'] = $this->temperature;
        }

        if ($this->topP !== null) {
            $result['top_p'] = $this->topP;
        }

        if ($this->maxTokens !== null) {
            $result['max_tokens'] = $this->maxTokens;
        }

        if ($this->maxCompletionTokens !== null) {
            $result['max_completion_tokens'] = $this->maxCompletionTokens;
        }

        if ($this->n !== null) {
            $result['n'] = $this->n;
        }

        if ($this->stop !== null) {
            $result['stop'] = $this->stop;
        }

        if ($this->stream !== null) {
            $result['stream'] = $this->stream;
        }

        if ($this->streamOptions !== null) {
            $result['stream_options'] = $this->streamOptions->toArray();
        }

        if ($this->frequencyPenalty !== null) {
            $result['frequency_penalty'] = $this->frequencyPenalty;
        }

        if ($this->presencePenalty !== null) {
            $result['presence_penalty'] = $this->presencePenalty;
        }

        if ($this->seed !== null) {
            $result['seed'] = $this->seed;
        }

        if ($this->logprobs !== null) {
            $result['logprobs'] = $this->logprobs;
        }

        if ($this->topLogprobs !== null) {
            $result['top_logprobs'] = $this->topLogprobs;
        }

        if ($this->logitBias !== null) {
            $result['logit_bias'] = $this->logitBias;
        }

        if ($this->user !== null) {
            $result['user'] = $this->user;
        }

        if ($this->store !== null) {
            $result['store'] = $this->store;
        }

        if ($this->metadata !== null) {
            $result['metadata'] = $this->metadata;
        }

        if ($this->serviceTier !== null) {
            $result['service_tier'] = $this->serviceTier;
        }

        if ($this->tools !== null) {
            $result['tools'] = array_map(static fn (ToolDefinition $t): array => $t->toArray(), $this->tools);
        }

        if ($this->toolChoice !== null) {
            $result['tool_choice'] = $this->toolChoice;
        }

        if ($this->parallelToolCalls !== null) {
            $result['parallel_tool_calls'] = $this->parallelToolCalls;
        }

        if ($this->responseFormat !== null) {
            $result['response_format'] = $this->responseFormat;
        }

        if ($this->modalities !== null) {
            $result['modalities'] = $this->modalities;
        }

        if ($this->audio !== null) {
            $result['audio'] = $this->audio->toArray();
        }

        if ($this->prediction !== null) {
            $result['prediction'] = $this->prediction;
        }

        if ($this->reasoningEffort !== null) {
            $result['reasoning_effort'] = $this->reasoningEffort;
        }

        if ($this->webSearch !== null) {
            $result['web_search'] = $this->webSearch;
        }

        if ($this->moderation !== null) {
            $result['moderation'] = $this->moderation;
        }

        return $result;
    }
}
