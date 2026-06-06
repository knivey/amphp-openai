# knivey/amphp-openai

[![CI](https://github.com/knivey/amphp-openai/actions/workflows/ci.yml/badge.svg)](https://github.com/knivey/amphp-openai/actions/workflows/ci.yml)
[![PHP Version](https://img.shields.io/packagist/php-v/knivey/amphp-openai)](https://packagist.org/packages/knivey/amphp-openai)
[![License](https://img.shields.io/packagist/l/knivey/amphp-openai)](https://packagist.org/packages/knivey/amphp-openai)

An async PHP wrapper around the [OpenAI Chat Completions API](https://platform.openai.com/docs/api-reference/chat), built on [amphp v3](https://amphp.org/). Designed as a reusable library for any PHP project that needs OpenAI integration with full API coverage.

## Requirements

- PHP 8.3+
- [amphp/http-client](https://amphp.org/http-client) ^5.0

## Installation

```bash
composer require knivey/amphp-openai
```

## Quick Start

```php
use Knivey\OpenAi\OpenAiClient;
use Knivey\OpenAi\Request\ChatRequest;
use Knivey\OpenAi\Request\Message;

$client = new OpenAiClient('sk-...');

$response = $client->chatCompletion(new ChatRequest(
    model: 'gpt-4o',
    messages: [
        Message::system('You are a helpful assistant.'),
        Message::user('Hello!'),
    ],
));

echo $response->choices[0]->message->content;
echo $response->usage->totalTokens . " tokens used\n";
```

## Streaming

```php
$stream = $client->chatCompletionStream(new ChatRequest(
    model: 'gpt-4o',
    messages: [Message::user('Tell me a story')],
    streamOptions: new StreamingOptions(includeUsage: true),
));

foreach ($stream as $chunk) {
    echo $chunk->choices[0]->delta['content'] ?? '';
}
```

## Multimodal (Images, Audio, Files)

```php
use Knivey\OpenAi\Request\Content\TextPart;
use Knivey\OpenAi\Request\Content\ImagePart;
use Knivey\OpenAi\Request\Content\AudioPart;
use Knivey\OpenAi\Request\Content\FilePart;

$response = $client->chatCompletion(new ChatRequest(
    model: 'gpt-4o',
    messages: [
        Message::user([
            new TextPart('What is in this image?'),
            ImagePart::url('https://example.com/photo.jpg', 'high'),
            ImagePart::base64($base64Data, 'image/png'),
        ]),
    ],
));
```

## Tool Calling

```php
use Knivey\OpenAi\Request\Tool\FunctionTool;
use Knivey\OpenAi\Request\Tool\CustomTool;

$tools = [
    new FunctionTool(
        'get_weather',
        description: 'Get the current weather',
        parameters: [
            'type' => 'object',
            'properties' => [
                'location' => ['type' => 'string'],
            ],
            'required' => ['location'],
        ],
        strict: true,
    ),
    new CustomTool('my_custom_tool', description: 'A custom tool'),
];

$response = $client->chatCompletion(new ChatRequest(
    model: 'gpt-4o',
    messages: [Message::user('What is the weather in SF?')],
    tools: $tools,
    toolChoice: 'auto',
));

$toolCall = $response->choices[0]->message->toolCalls[0];
echo $toolCall->function['name'];     // get_weather
echo $toolCall->function['arguments']; // {"location":"San Francisco"}
```

## Audio Output

```php
use Knivey\OpenAi\Request\Audio\AudioOutputOptions;

$response = $client->chatCompletion(new ChatRequest(
    model: 'gpt-4o-audio-preview',
    messages: [Message::user('Say hello in a friendly voice')],
    modalities: ['text', 'audio'],
    audio: new AudioOutputOptions('alloy', 'mp3'),
));

$audio = $response->choices[0]->message->audio;
echo $audio['transcript'];
```

## Custom Base URL

Works with any OpenAI-compatible API (Ollama, Together, Groq, etc.):

```php
$client = new OpenAiClient(
    apiKey: 'your-key',
    baseUrl: 'https://api.groq.com/openai/v1',
);
```

## Error Handling

```php
use Knivey\OpenAi\Exception\RateLimitException;
use Knivey\OpenAi\Exception\AuthenticationException;
use Knivey\OpenAi\Exception\ApiException;

try {
    $response = $client->chatCompletion($request);
} catch (RateLimitException $e) {
    // 429 — automatically retried up to 3 times with Retry-After header
} catch (AuthenticationException $e) {
    // 401/403
} catch (ApiException $e) {
    // All other HTTP errors
    echo $e->getStatusCode();
    echo $e->getResponseBody();
}
```

## Cancellation

```php
use Amp\Cancellation;

$response = $client->chatCompletion($request, cancellation: $cancellation);
```

## All Request Parameters

`ChatRequest` supports the full Chat Completions API surface:

| Parameter | Type | Description |
|---|---|---|
| `model` | `string` | Model ID (required) |
| `messages` | `list<Message>` | Conversation messages (required) |
| `temperature` | `?float` | Sampling temperature (0-2) |
| `topP` | `?float` | Nucleus sampling threshold |
| `maxTokens` | `?int` | Max tokens to generate |
| `maxCompletionTokens` | `?int` | Upper bound including reasoning |
| `n` | `?int` | Number of choices |
| `stop` | `string\|list<string>\|null` | Stop sequences |
| `stream` | `?bool` | Enable streaming (set automatically) |
| `streamOptions` | `?StreamingOptions` | Stream options (include_usage, etc.) |
| `tools` | `list<ToolDefinition>\|null` | Available tools |
| `toolChoice` | `string\|array\|null` | Tool selection mode |
| `parallelToolCalls` | `?bool` | Allow parallel tool calls |
| `responseFormat` | `?array` | Structured output format |
| `logprobs` | `?bool` | Return log probabilities |
| `topLogprobs` | `?int` | Number of top logprobs (0-20) |
| `logitBias` | `array<string, int>\|null` | Token likelihood modification |
| `seed` | `?int` | Deterministic sampling seed |
| `frequencyPenalty` | `?float` | Frequency penalty (-2.0 to 2.0) |
| `presencePenalty` | `?float` | Presence penalty (-2.0 to 2.0) |
| `modalities` | `list<string>\|null` | Output modalities (text, audio) |
| `audio` | `?AudioOutputOptions` | Audio output configuration |
| `store` | `?bool` | Store completion for retrieval |
| `metadata` | `?array` | Developer metadata |
| `serviceTier` | `?string` | Processing tier |
| `user` | `?string` | End-user identifier |
| `prediction` | `?array` | Predicted output for latency |
| `reasoningEffort` | `?string` | Reasoning effort (low/medium/high) |
| `webSearch` | `?array` | Web search tool config |
| `moderation` | `?array` | Moderation settings |

## Development

```bash
composer test        # Run tests
composer stan        # PHPStan level 9
composer cs:check    # PSR-12 check
composer cs:fix      # PSR-12 fix
composer check       # Run all checks
```

## License

MIT
