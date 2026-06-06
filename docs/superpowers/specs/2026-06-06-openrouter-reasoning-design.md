# OpenRouter Reasoning Adaptation

## Problem

OpenRouter uses a non-standard `reasoning` object for controlling reasoning effort, while the standard OpenAI API uses a flat `reasoning_effort` string. The library currently only supports the OpenAI format. When users point the library at OpenRouter's endpoint, `reasoning_effort` may not work correctly for all models, and users lose access to OpenRouter's richer reasoning controls (max_tokens, exclude, enabled).

## Solution

Add a `Reasoning` value object and a `Provider` enum. Auto-detect OpenRouter from the base URL and translate the request format accordingly. The existing `reasoningEffort` string property is preserved for backward compatibility.

## New Components

### `Reasoning` value object (`src/Request/Reasoning.php`)

A readonly class with optional properties:

- `?string $effort` — one of: xhigh, high, medium, low, minimal, none
- `?int $maxTokens` — token budget for Anthropic/Gemini-style reasoning
- `?bool $exclude` — use reasoning internally but omit from response
- `?bool $enabled` — enable reasoning with default parameters

Named constructors for convenience:

- `Reasoning::effort('high')`
- `Reasoning::maxTokens(2000)`
- `Reasoning::enabled()`

`toArray()` method returns an associative array with only non-null fields, using snake_case keys (effort, max_tokens, exclude, enabled).

### `Provider` enum (`src/Provider.php`)

```php
enum Provider: string {
    case OPENAI = 'openai';
    case OPENROUTER = 'openrouter';
}
```

## Changes to Existing Components

### `OpenAiClient`

- Add optional `?Provider $provider` constructor parameter
- Auto-detection logic: if `$provider` is null, check if `$baseUrl` contains `openrouter.ai`; if so, use `OPENROUTER`, otherwise `OPENAI`
- Store resolved provider for use in request serialization
- Pass provider to `ChatRequest::toArray()` when building request bodies

### `ChatRequest`

- Add `?Reasoning $reasoning` constructor parameter (alongside existing `?string $reasoningEffort`)
- Update `withMessages()` to carry the new `$reasoning` property
- Change `toArray()` signature to `toArray(?Provider $provider = null)` (defaults to `OPENAI`)

### Serialization Logic (`toArray`)

The translation depends on the resolved provider:

**When `Reasoning` object is set** (takes precedence over `reasoningEffort`):
- OpenRouter: output `reasoning: { effort, max_tokens, exclude, enabled }` (only non-null fields)
- OpenAI: output `reasoning_effort` from `$reasoning->effort` if set; other Reasoning fields are ignored (not supported by OpenAI)

**When only `reasoningEffort` string is set** (legacy):
- OpenRouter: output `reasoning: { effort: $reasoningEffort }`
- OpenAI: output `reasoning_effort: $reasoningEffort` (current behavior, unchanged)

**When neither is set**: no reasoning fields in output.

**Invariant**: Never emit both `reasoning_effort` and `reasoning` in the same request body.

## Tests

- `ReasoningTest`: construction, named constructors, toArray with various combinations of fields
- `ChatRequestTest`:
  - toArray with Reasoning object + OPENAI provider → `reasoning_effort`
  - toArray with Reasoning object + OPENROUTER provider → `reasoning` object
  - toArray with reasoningEffort string + OPENROUTER → `reasoning.effort`
  - toArray with reasoningEffort string + OPENAI → `reasoning_effort`
  - toArray with both set → Reasoning takes precedence
  - toArray with neither set → no reasoning fields
  - Ensure `reasoning_effort` and `reasoning` never both appear
- `OpenAiClientTest`:
  - Auto-detection from URL containing `openrouter.ai`
  - Auto-detection defaults to OPENAI for non-OpenRouter URLs
  - Explicit provider parameter overrides auto-detection

## Files Changed

- `src/Request/Reasoning.php` — new
- `src/Provider.php` — new
- `src/Request/ChatRequest.php` — add `$reasoning` property, update `withMessages()`, update `toArray()`
- `src/OpenAiClient.php` — add `$provider` param, auto-detection, pass provider to toArray
- `tests/Request/ReasoningTest.php` — new
- `tests/Request/ChatRequestTest.php` — update
- `tests/OpenAiClientTest.php` — update
