# Reflection-Based Tool Calling

## Summary

Use PHP reflection to generate OpenAI function-calling tool schemas automatically from PHP callables, with a registry for dispatching tool calls back to those callables, and client integration for a full automatic tool-call loop.

## Motivation

Currently, defining tools requires manually writing JSON Schema arrays:

```php
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
);
```

This is verbose, error-prone, and duplicates information that already exists in PHP type signatures. Reflection can derive the schema from the callable's type hints, making tool definitions concise and type-safe.

## New Components

### ReflectionTool

`src/Request/Tool/ReflectionTool.php`

Wraps a callable and generates a JSON Schema tool definition from its type hints. Implements `ToolDefinition` so it works directly in `ChatRequest::tools`.

**Construction:**

- `ReflectionTool::fromCallable(callable $callable, ?string $name = null, ?string $description = null, ?bool $strict = null): self`
  - Name: from `$name` param, or derived from the function/method name. Closures require an explicit `name` (reflection yields `Closure`).
  - Description: from `$description` param, or `#[ToolDescription]` attribute if on a named function/method, or `null`.
- `ReflectionTool::fromMethod(array|string $method, ?string $name = null, ?string $description = null, ?bool $strict = null): self`
  - Accepts `[$object, 'methodName']`, `[ClassName::class, 'methodName']`, or `'ClassName::methodName'`.
  - Name: from `$name` param, or the method name.
  - Description: from `$description` param, or `#[ToolDescription]` attribute on the method.

**Schema generation from type hints:**

| PHP Type | JSON Schema Type |
|---|---|
| `string` | `string` |
| `int` | `integer` |
| `float` | `number` |
| `bool` | `boolean` |
| `array` | `object` |
| Backed Enum (`Unit: string`) | `string` + `enum: [...]` with case values |
| Backed Enum (`Status: int`) | `integer` + `enum: [...]` with case values |
| No type hint | Omitted from properties. Throws `InvalidArgumentException` during construction. |

**Required vs optional:** Parameters without a default value are `required`. Parameters with a default value are optional and include `"default"` in the schema.

**`invoke(string $argumentsJson): mixed`**

Takes a JSON string (as returned by the API in `toolCall->function['arguments']`), decodes it, validates required params are present, and calls the wrapped callable with the arguments. Returns the callable's return value.

### Attributes

`src/Request/Tool/Attribute/ToolDescription.php`

```php
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
class ToolDescription
{
    public function __construct(public string $description) {}
}
```

`src/Request/Tool/Attribute/ToolParam.php`

```php
#[Attribute(Attribute::TARGET_PARAMETER)]
class ToolParam
{
    public function __construct(
        public ?string $description = null,
        public ?string $type = null,
        public ?array $enum = null,
    ) {}
}
```

**Resolution order for description:** explicit `$description` param in `fromCallable()`/`fromMethod()` > `#[ToolDescription]` attribute > `null`.

**Resolution order for param metadata:** `#[ToolParam]` overrides auto-detected values. `ToolParam::$type` overrides the type hint. `ToolParam::$description` adds a `description` field to the parameter schema. `ToolParam::$enum` adds enum values (also auto-detected for backed enums).

### ToolRegistry

`src/Request/Tool/ToolRegistry.php`

Maps tool names to `ReflectionTool` instances. Provides dispatch and schema extraction.

**API:**

- `static create(): self` — factory
- `add(ReflectionTool $tool): self` — register a tool, fluent
- `get(string $name): ReflectionTool` — get a tool by name
- `has(string $name): bool` — check if a tool exists
- `getTools(): list<ToolDefinition>` — all tools as `ToolDefinition` instances, for use in `ChatRequest::tools`
- `dispatch(string $name, string $argumentsJson): mixed` — look up a tool and invoke it
- `dispatchAll(list<ToolCall> $toolCalls): array<string, mixed>` — accepts the `list<ToolCall>` from `message->toolCalls`, dispatches each, returns associative array keyed by tool call ID

**Error handling:** Dispatching an unknown tool name throws an exception with the name. If `invoke()` fails, the exception propagates.

### Client Integration

`OpenAiClient::chatCompletionWithTools(ChatRequest $request, ToolRegistry $registry, int $maxIterations = 10): ChatResponse`

**Loop:**

1. Send the request via `chatCompletion()`.
2. Check if any choice has `toolCalls`.
3. If yes: for each tool call, `dispatch()` it via the registry. Append the assistant message (with tool calls) and a tool message (with the result) to the conversation.
4. Re-send with updated messages, same tools and toolChoice.
5. Repeat until the API returns a response with no tool calls or `maxIterations` is exceeded.
6. Return the final `ChatResponse`.

**Error handling:**

- `maxIterations` exceeded: throws an exception with the last response attached.
- Unknown tool name: throws an exception.
- `invoke()` failure: exception propagates, user can catch.

**Streaming:** Not in scope for initial implementation. The tool loop requires full responses. Users can manually implement streaming + tools.

## Usage Examples

### Closure-based with full loop

```php
use Knivey\OpenAi\Request\Tool\ReflectionTool;
use Knivey\OpenAi\Request\Tool\ToolRegistry;
use Knivey\OpenAi\Request\Message;
use Knivey\OpenAi\Request\ChatRequest;

$registry = ToolRegistry::create()
    ->add(ReflectionTool::fromCallable(
        fn(string $location, string $unit = 'celsius'): string => getWeather($location, $unit),
        name: 'get_weather',
        description: 'Get the current weather for a location',
    ))
    ->add(ReflectionTool::fromCallable(
        fn(string $query, int $limit = 5): array => searchWeb($query, $limit),
        name: 'search_web',
        description: 'Search the web',
    ));

$response = $client->chatCompletionWithTools(
    new ChatRequest(
        model: 'gpt-4o',
        messages: [Message::user('What is the weather in SF?')],
        tools: $registry->getTools(),
    ),
    $registry,
);
```

### Attribute-based class tools

```php
use Knivey\OpenAi\Request\Tool\Attribute\ToolDescription;
use Knivey\OpenAi\Request\Tool\Attribute\ToolParam;

enum Unit: string {
    case Celsius = 'celsius';
    case Fahrenheit = 'fahrenheit';
}

class WeatherTools {
    #[ToolDescription('Get the current weather')]
    public function get_weather(
        string $location,
        Unit $unit = Unit::Celsius,
    ): string {
        return getWeather($location, $unit->value);
    }
}

$tools = new WeatherTools();
$registry = ToolRegistry::create()
    ->add(ReflectionTool::fromMethod([$tools, 'get_weather']));

$response = $client->chatCompletionWithTools(
    new ChatRequest(model: 'gpt-4o', messages: [...], tools: $registry->getTools()),
    $registry,
);
```

Enum types are auto-detected: `Unit` generates `"type": "string", "enum": ["celsius", "fahrenheit"], "default": "celsius"`.

### Manual dispatch (no auto-loop)

```php
$registry = ToolRegistry::create()
    ->add(ReflectionTool::fromCallable(
        fn(string $location): string => getWeather($location),
        name: 'get_weather',
        description: 'Get weather',
    ));

$response = $client->chatCompletion(new ChatRequest(
    model: 'gpt-4o',
    messages: [Message::user('Weather in SF?')],
    tools: $registry->getTools(),
));

if ($tc = $response->choices[0]->message->toolCalls) {
    $results = $registry->dispatchAll($tc);
    foreach ($tc as $call) {
        $messages[] = Message::tool(
            (string) $results[$call->id],
            $call->id,
        );
    }
}
```

### Schema generation only (no registry, no dispatch)

```php
$tool = ReflectionTool::fromCallable(
    fn(string $location): string => getWeather($location),
    name: 'get_weather',
    description: 'Get weather',
);
$tools = [$tool]; // implements ToolDefinition
```

## File Layout

```
src/Request/Tool/
  ToolDefinition.php              (existing, unchanged)
  FunctionTool.php                (existing, unchanged)
  CustomTool.php                  (existing, unchanged)
  ReflectionTool.php              (new)
  ToolRegistry.php                (new)
  Attribute/
    ToolDescription.php           (new)
    ToolParam.php                 (new)

tests/Request/Tool/
  ReflectionToolTest.php          (new)
  ToolRegistryTest.php            (new)
  Attribute/
    ToolDescriptionTest.php       (new)
    ToolParamTest.php             (new)

tests/OpenAiClientTest.php        (updated — add chatCompletionWithTools tests)
```

## Testing Strategy

- **ReflectionTool:** type mapping for each PHP type, required vs optional from defaults, backed enum detection (string and int), `#[ToolDescription]` attribute reading, `#[ToolParam]` overrides, `invoke()` with valid/invalid JSON, closure vs method construction, missing type hints throw, nullable types, default values in schema.
- **ToolRegistry:** add/get/has, `getTools()` returns correct list, `dispatch()` calls the right tool, `dispatchAll()` with multiple tool calls, unknown tool name throws, fluent interface.
- **Client integration:** mock HTTP to simulate tool call response followed by final response, verify loop produces correct message chain, max iterations throws.
- **Attributes:** verify attributes store and expose their values.

## Out of Scope

- Streaming + tool loop (users can implement manually)
- Nested object schemas from complex type hints (only scalar types, enums, and simple arrays)
- Automatic retry of failed tool invocations
- Parallel tool call handling beyond what the API already supports
