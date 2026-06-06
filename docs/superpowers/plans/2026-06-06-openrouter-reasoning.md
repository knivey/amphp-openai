# OpenRouter Reasoning Adaptation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add provider-aware reasoning parameter translation so the library works correctly with both OpenAI (flat `reasoning_effort`) and OpenRouter (structured `reasoning` object).

**Architecture:** New `Reasoning` value object and `Provider` enum. `ChatRequest.toArray()` gains a `?Provider` parameter and translates reasoning fields accordingly. `OpenAiClient` auto-detects OpenRouter from the base URL and passes the resolved provider to `toArray()`.

**Tech Stack:** PHP 8.5, PHPUnit 11, readonly classes, backed enums

---

### Task 1: Create Provider enum

**Files:**
- Create: `src/Provider.php`

- [ ] **Step 1: Create the Provider enum**

```php
<?php

namespace Knivey\OpenAi;

enum Provider: string
{
    case OPENAI = 'openai';
    case OPENROUTER = 'openrouter';
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Provider.php
git commit -m "feat: add Provider enum for OpenAI/OpenRouter detection"
```

---

### Task 2: Create Reasoning value object

**Files:**
- Create: `src/Request/Reasoning.php`
- Create: `tests/Request/ReasoningTest.php`

- [ ] **Step 1: Write tests for Reasoning**

```php
<?php

namespace Knivey\OpenAi\Tests\Request;

use Knivey\OpenAi\Request\Reasoning;
use PHPUnit\Framework\TestCase;

class ReasoningTest extends TestCase
{
    public function testEffortNamedConstructor(): void
    {
        $r = Reasoning::effort('high');
        $this->assertSame('high', $r->effort);
        $this->assertNull($r->maxTokens);
        $this->assertNull($r->exclude);
        $this->assertNull($r->enabled);
    }

    public function testMaxTokensNamedConstructor(): void
    {
        $r = Reasoning::maxTokens(2000);
        $this->assertNull($r->effort);
        $this->assertSame(2000, $r->maxTokens);
    }

    public function testEnabledNamedConstructor(): void
    {
        $r = Reasoning::enabled();
        $this->assertTrue($r->enabled);
        $this->assertNull($r->effort);
    }

    public function testConstructorWithAllFields(): void
    {
        $r = new Reasoning(
            effort: 'high',
            maxTokens: 8000,
            exclude: true,
            enabled: true,
        );
        $this->assertSame('high', $r->effort);
        $this->assertSame(8000, $r->maxTokens);
        $this->assertTrue($r->exclude);
        $this->assertTrue($r->enabled);
    }

    public function testToArrayOnlyIncludesNonNullFields(): void
    {
        $r = Reasoning::effort('low');
        $arr = $r->toArray();
        $this->assertSame(['effort' => 'low'], $arr);
    }

    public function testToArrayWithMultipleFields(): void
    {
        $r = new Reasoning(effort: 'high', exclude: true);
        $arr = $r->toArray();
        $this->assertSame(['effort' => 'high', 'exclude' => true], $arr);
    }

    public function testToArrayWithAllFields(): void
    {
        $r = new Reasoning(effort: 'medium', maxTokens: 5000, exclude: false, enabled: true);
        $arr = $r->toArray();
        $this->assertSame([
            'effort' => 'medium',
            'max_tokens' => 5000,
            'exclude' => false,
            'enabled' => true,
        ], $arr);
    }

    public function testToArrayWithOnlyMaxTokens(): void
    {
        $r = Reasoning::maxTokens(1024);
        $arr = $r->toArray();
        $this->assertSame(['max_tokens' => 1024], $arr);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit tests/Request/ReasoningTest.php`
Expected: FATAL ERROR — class `Reasoning` not found

- [ ] **Step 3: Implement Reasoning**

```php
<?php

namespace Knivey\OpenAi\Request;

readonly class Reasoning
{
    public function __construct(
        public ?string $effort = null,
        public ?int $maxTokens = null,
        public ?bool $exclude = null,
        public ?bool $enabled = null,
    ) {
    }

    public static function effort(string $effort): self
    {
        return new self(effort: $effort);
    }

    public static function maxTokens(int $maxTokens): self
    {
        return new self(maxTokens: $maxTokens);
    }

    public static function enabled(): self
    {
        return new self(enabled: true);
    }

    /**
     * @return array<string, bool|int|string>
     */
    public function toArray(): array
    {
        $result = [];
        if ($this->effort !== null) {
            $result['effort'] = $this->effort;
        }
        if ($this->maxTokens !== null) {
            $result['max_tokens'] = $this->maxTokens;
        }
        if ($this->exclude !== null) {
            $result['exclude'] = $this->exclude;
        }
        if ($this->enabled !== null) {
            $result['enabled'] = $this->enabled;
        }
        return $result;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit tests/Request/ReasoningTest.php`
Expected: All tests PASS

- [ ] **Step 5: Commit**

```bash
git add src/Request/Reasoning.php tests/Request/ReasoningTest.php
git commit -m "feat: add Reasoning value object with effort, maxTokens, exclude, enabled"
```

---

### Task 3: Add Provider param and Reasoning property to ChatRequest

**Files:**
- Modify: `src/Request/ChatRequest.php`
- Modify: `tests/Request/ChatRequestTest.php`

- [ ] **Step 1: Write failing tests for provider-aware reasoning translation**

Add these tests to `tests/Request/ChatRequestTest.php`. Add the following imports at the top of the file:

```php
use Knivey\OpenAi\Provider;
use Knivey\OpenAi\Request\Reasoning;
```

Then add these test methods to the `ChatRequestTest` class:

```php
    public function testReasoningObjectWithOpenAIProviderOutputsReasoningEffort(): void
    {
        $req = new ChatRequest(
            model: 'gpt-4',
            messages: [Message::user('hello')],
            reasoning: Reasoning::effort('high'),
        );
        $arr = $req->toArray(Provider::OPENAI);
        $this->assertSame('high', $arr['reasoning_effort']);
        $this->assertArrayNotHasKey('reasoning', $arr);
    }

    public function testReasoningObjectWithOpenRouterProviderOutputsReasoningObject(): void
    {
        $req = new ChatRequest(
            model: 'gpt-4',
            messages: [Message::user('hello')],
            reasoning: Reasoning::effort('high'),
        );
        $arr = $req->toArray(Provider::OPENROUTER);
        $this->assertArrayNotHasKey('reasoning_effort', $arr);
        $this->assertSame(['effort' => 'high'], $arr['reasoning']);
    }

    public function testReasoningObjectWithMaxTokensOpenRouter(): void
    {
        $req = new ChatRequest(
            model: 'gpt-4',
            messages: [Message::user('hello')],
            reasoning: Reasoning::maxTokens(8000),
        );
        $arr = $req->toArray(Provider::OPENROUTER);
        $this->assertArrayNotHasKey('reasoning_effort', $arr);
        $this->assertSame(['max_tokens' => 8000], $arr['reasoning']);
    }

    public function testReasoningObjectWithMaxTokensOpenAIIgnoresMaxTokens(): void
    {
        $req = new ChatRequest(
            model: 'gpt-4',
            messages: [Message::user('hello')],
            reasoning: Reasoning::maxTokens(8000),
        );
        $arr = $req->toArray(Provider::OPENAI);
        $this->assertArrayNotHasKey('reasoning', $arr);
        $this->assertArrayNotHasKey('reasoning_effort', $arr);
    }

    public function testReasoningObjectWithExcludeAndEffortOpenRouter(): void
    {
        $req = new ChatRequest(
            model: 'gpt-4',
            messages: [Message::user('hello')],
            reasoning: new Reasoning(effort: 'high', exclude: true),
        );
        $arr = $req->toArray(Provider::OPENROUTER);
        $this->assertSame(['effort' => 'high', 'exclude' => true], $arr['reasoning']);
        $this->assertArrayNotHasKey('reasoning_effort', $arr);
    }

    public function testReasoningObjectTakesPrecedenceOverReasoningEffortForOpenAI(): void
    {
        $req = new ChatRequest(
            model: 'gpt-4',
            messages: [Message::user('hello')],
            reasoningEffort: 'low',
            reasoning: Reasoning::effort('high'),
        );
        $arr = $req->toArray(Provider::OPENAI);
        $this->assertSame('high', $arr['reasoning_effort']);
        $this->assertArrayNotHasKey('reasoning', $arr);
    }

    public function testReasoningObjectTakesPrecedenceOverReasoningEffortForOpenRouter(): void
    {
        $req = new ChatRequest(
            model: 'gpt-4',
            messages: [Message::user('hello')],
            reasoningEffort: 'low',
            reasoning: Reasoning::effort('high'),
        );
        $arr = $req->toArray(Provider::OPENROUTER);
        $this->assertSame(['effort' => 'high'], $arr['reasoning']);
        $this->assertArrayNotHasKey('reasoning_effort', $arr);
    }

    public function testLegacyReasoningEffortWithOpenRouterTranslatedToReasoningObject(): void
    {
        $req = new ChatRequest(
            model: 'gpt-4',
            messages: [Message::user('hello')],
            reasoningEffort: 'medium',
        );
        $arr = $req->toArray(Provider::OPENROUTER);
        $this->assertArrayNotHasKey('reasoning_effort', $arr);
        $this->assertSame(['effort' => 'medium'], $arr['reasoning']);
    }

    public function testLegacyReasoningEffortWithOpenAIUnchanged(): void
    {
        $req = new ChatRequest(
            model: 'gpt-4',
            messages: [Message::user('hello')],
            reasoningEffort: 'high',
        );
        $arr = $req->toArray(Provider::OPENAI);
        $this->assertSame('high', $arr['reasoning_effort']);
        $this->assertArrayNotHasKey('reasoning', $arr);
    }

    public function testNoReasoningFieldsOmitsBoth(): void
    {
        $req = new ChatRequest(
            model: 'gpt-4',
            messages: [Message::user('hello')],
        );
        $arr = $req->toArray(Provider::OPENAI);
        $this->assertArrayNotHasKey('reasoning_effort', $arr);
        $this->assertArrayNotHasKey('reasoning', $arr);
    }

    public function testNoReasoningFieldsOmitsBothForOpenRouter(): void
    {
        $req = new ChatRequest(
            model: 'gpt-4',
            messages: [Message::user('hello')],
        );
        $arr = $req->toArray(Provider::OPENROUTER);
        $this->assertArrayNotHasKey('reasoning_effort', $arr);
        $this->assertArrayNotHasKey('reasoning', $arr);
    }

    public function testToArrayWithoutProviderDefaultsToOpenAI(): void
    {
        $req = new ChatRequest(
            model: 'gpt-4',
            messages: [Message::user('hello')],
            reasoningEffort: 'high',
        );
        $arr = $req->toArray();
        $this->assertSame('high', $arr['reasoning_effort']);
        $this->assertArrayNotHasKey('reasoning', $arr);
    }
```

Also update the existing `testFullRequestWithAllOptions` test — change line 96's assertion. Since `toArray()` is called without a provider (defaults to OPENAI), the existing `reasoningEffort: 'high'` should still output `reasoning_effort`. This test should pass unchanged — no modification needed.

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit tests/Request/ChatRequestTest.php`
Expected: FAIL — `Reasoning` class not found / undefined constant `Provider::OPENAI`

- [ ] **Step 3: Modify ChatRequest to add `reasoning` property and update `toArray()` signature**

In `src/Request/ChatRequest.php`:

1. Add `use Knivey\OpenAi\Provider;` import

2. Add `?Reasoning $reasoning = null` parameter to the constructor (after `$moderation`)

3. Add `reasoning: $this->reasoning` to `withMessages()` (after `moderation: $this->moderation`)

4. Change `toArray()` signature to `toArray(?Provider $provider = null): array` and replace the reasoning section at the end of `toArray()` with:

```php
        $provider ??= Provider::OPENAI;

        if ($this->reasoning !== null) {
            if ($provider === Provider::OPENROUTER) {
                $reasoningArr = $this->reasoning->toArray();
                if ($reasoningArr !== []) {
                    $result['reasoning'] = $reasoningArr;
                }
            } else {
                if ($this->reasoning->effort !== null) {
                    $result['reasoning_effort'] = $this->reasoning->effort;
                }
            }
        } elseif ($this->reasoningEffort !== null) {
            if ($provider === Provider::OPENROUTER) {
                $result['reasoning'] = ['effort' => $this->reasoningEffort];
            } else {
                $result['reasoning_effort'] = $this->reasoningEffort;
            }
        }
```

This replaces the previous block:

```php
        if ($this->reasoningEffort !== null) {
            $result['reasoning_effort'] = $this->reasoningEffort;
        }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit tests/Request/ChatRequestTest.php`
Expected: All tests PASS

- [ ] **Step 5: Commit**

```bash
git add src/Request/ChatRequest.php tests/Request/ChatRequestTest.php
git commit -m "feat: add Reasoning property and provider-aware toArray to ChatRequest"
```

---

### Task 4: Add provider detection to OpenAiClient

**Files:**
- Modify: `src/OpenAiClient.php`
- Modify: `tests/OpenAiClientTest.php`

- [ ] **Step 1: Write failing tests for provider auto-detection and explicit override**

Add this import to `tests/OpenAiClientTest.php`:

```php
use Knivey\OpenAi\Provider;
```

Add these test methods to the `OpenAiClientTest` class:

```php
    public function testProviderDefaultsToOpenAI(): void
    {
        $mock = $this->createMockClient((string) json_encode(self::SUCCESS_RESPONSE));
        $client = new OpenAiClient('test-key', httpClient: new \Knivey\OpenAi\HttpClient('test-key', $mock));
        $this->assertSame(Provider::OPENAI, $client->getProvider());
    }

    public function testProviderAutoDetectsOpenRouter(): void
    {
        $mock = $this->createMockClient((string) json_encode(self::SUCCESS_RESPONSE));
        $client = new OpenAiClient(
            'test-key',
            baseUrl: 'https://openrouter.ai/api/v1',
            httpClient: new \Knivey\OpenAi\HttpClient('test-key', $mock),
        );
        $this->assertSame(Provider::OPENROUTER, $client->getProvider());
    }

    public function testProviderExplicitOverridesAutoDetection(): void
    {
        $mock = $this->createMockClient((string) json_encode(self::SUCCESS_RESPONSE));
        $client = new OpenAiClient(
            'test-key',
            baseUrl: 'https://openrouter.ai/api/v1',
            provider: Provider::OPENAI,
            httpClient: new \Knivey\OpenAi\HttpClient('test-key', $mock),
        );
        $this->assertSame(Provider::OPENAI, $client->getProvider());
    }

    public function testProviderAutoDetectsOpenRouterSubdomain(): void
    {
        $mock = $this->createMockClient((string) json_encode(self::SUCCESS_RESPONSE));
        $client = new OpenAiClient(
            'test-key',
            baseUrl: 'https://custom.openrouter.ai/v1',
            httpClient: new \Knivey\OpenAi\HttpClient('test-key', $mock),
        );
        $this->assertSame(Provider::OPENROUTER, $client->getProvider());
    }

    public function testProviderNonOpenRouterUrlDefaultsToOpenAI(): void
    {
        $mock = $this->createMockClient((string) json_encode(self::SUCCESS_RESPONSE));
        $client = new OpenAiClient(
            'test-key',
            baseUrl: 'https://api.groq.com/openai/v1',
            httpClient: new \Knivey\OpenAi\HttpClient('test-key', $mock),
        );
        $this->assertSame(Provider::OPENAI, $client->getProvider());
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit tests/OpenAiClientTest.php`
Expected: FAIL — `getProvider` method does not exist

- [ ] **Step 3: Implement provider detection in OpenAiClient**

In `src/OpenAiClient.php`:

1. Add `use Knivey\OpenAi\Provider;` (already in same namespace, so just reference `Provider`)

2. Add `$provider` parameter to constructor and resolve it:

Replace the constructor with:

```php
    private readonly Provider $provider;

    private readonly string $baseUrl;

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
```

3. Update `chatCompletion()` to pass provider to `toArray()`:

```php
    public function chatCompletion(ChatRequest $request): ChatResponse
    {
        $body = $request->toArray($this->provider);
        $data = $this->getHttpClient()->post($this->baseUrl . '/chat/completions', $body);

        return ChatResponse::fromApiResponse($data);
    }
```

4. Update `chatCompletionStream()` to pass provider to `toArray()`:

Change `$body = $request->toArray();` to `$body = $request->toArray($this->provider);`

5. In `chatCompletionWithTools()`, the inner loop calls `$this->chatCompletion()` which already passes the provider, so no changes needed there.

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit tests/OpenAiClientTest.php`
Expected: All tests PASS

- [ ] **Step 5: Commit**

```bash
git add src/OpenAiClient.php tests/OpenAiClientTest.php
git commit -m "feat: add provider auto-detection and explicit override to OpenAiClient"
```

---

### Task 5: Run full test suite and static analysis

**Files:**
- No new files

- [ ] **Step 1: Run all tests**

Run: `vendor/bin/phpunit`
Expected: All tests PASS

- [ ] **Step 2: Run PHPStan**

Run: `vendor/bin/phpstan analyse`
Expected: No errors

- [ ] **Step 3: Run CS fixer check**

Run: `vendor/bin/php-cs-fixer check`
Expected: No issues (if there are formatting issues, run `vendor/bin/php-cs-fixer fix` then re-check)

- [ ] **Step 4: Commit any CS fixes if needed**

```bash
git add -u
git commit -m "style: fix coding style"
```
