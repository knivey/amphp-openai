# Reflection-Based Tool Calling Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Use PHP reflection to auto-generate OpenAI function-calling tool schemas from PHP callables, with a registry for dispatching tool calls and client integration for a full auto-loop.

**Architecture:** New `ReflectionTool` class wraps callables and uses `ReflectionFunction`/`ReflectionMethod` to derive JSON Schema from type hints. Attributes (`#[ToolDescription]`, `#[ToolParam]`) provide optional metadata. `ToolRegistry` maps tool names to `ReflectionTool` instances for dispatch. `OpenAiClient::chatCompletionWithTools()` runs the full tool-call loop.

**Tech Stack:** PHP 8.3+, PHPUnit 11, existing amphp/http-client stack.

---

### Task 1: Attributes — ToolDescription and ToolParam

**Files:**
- Create: `src/Request/Tool/Attribute/ToolDescription.php`
- Create: `src/Request/Tool/Attribute/ToolParam.php`
- Create: `tests/Request/Tool/Attribute/ToolDescriptionTest.php`
- Create: `tests/Request/Tool/Attribute/ToolParamTest.php`

- [ ] **Step 1: Write tests for ToolDescription**

```php
<?php

namespace Knivey\OpenAi\Tests\Request\Tool\Attribute;

use Knivey\OpenAi\Request\Tool\Attribute\ToolDescription;
use PHPUnit\Framework\TestCase;

class ToolDescriptionTest extends TestCase
{
    public function testStoresDescription(): void
    {
        $attr = new ToolDescription('Get the weather');
        $this->assertSame('Get the weather', $attr->description);
    }

    public function testIsAttribute(): void
    {
        $ref = new \ReflectionClass(ToolDescription::class);
        $attrs = $ref->getAttributes(\Attribute::class);
        $this->assertCount(1, $attrs);
        $instance = $attrs[0]->newInstance();
        $this->assertSame(
            \Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION,
            $instance->flags,
        );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Request/Tool/Attribute/ToolDescriptionTest.php`
Expected: FAIL — class does not exist

- [ ] **Step 3: Write ToolDescription attribute**

```php
<?php

namespace Knivey\OpenAi\Request\Tool\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION)]
readonly class ToolDescription
{
    public function __construct(public string $description) {}
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Request/Tool/Attribute/ToolDescriptionTest.php`
Expected: PASS

- [ ] **Step 5: Write tests for ToolParam**

```php
<?php

namespace Knivey\OpenAi\Tests\Request\Tool\Attribute;

use Knivey\OpenAi\Request\Tool\Attribute\ToolParam;
use PHPUnit\Framework\TestCase;

class ToolParamTest extends TestCase
{
    public function testAllNullDefaults(): void
    {
        $attr = new ToolParam();
        $this->assertNull($attr->description);
        $this->assertNull($attr->type);
        $this->assertNull($attr->enum);
    }

    public function testAllSet(): void
    {
        $attr = new ToolParam(
            description: 'City name',
            type: 'string',
            enum: ['NYC', 'SF'],
        );
        $this->assertSame('City name', $attr->description);
        $this->assertSame('string', $attr->type);
        $this->assertSame(['NYC', 'SF'], $attr->enum);
    }

    public function testIsParameterAttribute(): void
    {
        $ref = new \ReflectionClass(ToolParam::class);
        $attrs = $ref->getAttributes(\Attribute::class);
        $this->assertCount(1, $attrs);
        $instance = $attrs[0]->newInstance();
        $this->assertSame(\Attribute::TARGET_PARAMETER, $instance->flags);
    }
}
```

- [ ] **Step 6: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Request/Tool/Attribute/ToolParamTest.php`
Expected: FAIL — class does not exist

- [ ] **Step 7: Write ToolParam attribute**

```php
<?php

namespace Knivey\OpenAi\Request\Tool\Attribute;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
readonly class ToolParam
{
    public function __construct(
        public ?string $description = null,
        public ?string $type = null,
        public ?array $enum = null,
    ) {}
}
```

- [ ] **Step 8: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Request/Tool/Attribute/ToolParamTest.php`
Expected: PASS

- [ ] **Step 9: Commit**

```bash
git add src/Request/Tool/Attribute/ tests/Request/Tool/Attribute/
git commit -m "feat: add ToolDescription and ToolParam attributes"
```

---

### Task 2: ReflectionTool — Core Schema Generation

**Files:**
- Create: `src/Request/Tool/ReflectionTool.php`
- Create: `tests/Request/Tool/ReflectionToolTest.php`

- [ ] **Step 1: Write tests for basic schema generation from closures**

```php
<?php

namespace Knivey\OpenAi\Tests\Request\Tool;

use Knivey\OpenAi\Request\Tool\ReflectionTool;
use Knivey\OpenAi\Request\Tool\Attribute\ToolDescription;
use Knivey\OpenAi\Request\Tool\Attribute\ToolParam;
use PHPUnit\Framework\TestCase;

class ReflectionToolTest extends TestCase
{
    public function testFromClosureWithNameAndDescription(): void
    {
        $tool = ReflectionTool::fromCallable(
            fn(string $location): string => $location,
            name: 'get_weather',
            description: 'Get weather',
        );

        $this->assertSame('get_weather', $tool->name);
        $arr = $tool->toArray();
        $this->assertSame('function', $arr['type']);
        $fn = $arr['function'];
        $this->assertSame('get_weather', $fn['name']);
        $this->assertSame('Get weather', $fn['description']);
        $this->assertSame('object', $fn['parameters']['type']);
        $this->assertArrayHasKey('location', $fn['parameters']['properties']);
        $this->assertSame('string', $fn['parameters']['properties']['location']['type']);
        $this->assertSame(['location'], $fn['parameters']['required']);
    }

    public function testClosureWithoutNameThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ReflectionTool::fromCallable(fn(string $x): string => $x);
    }

    public function testOptionalParameterWithDefault(): void
    {
        $tool = ReflectionTool::fromCallable(
            fn(string $location, string $unit = 'celsius'): string => $location,
            name: 'get_weather',
        );

        $arr = $tool->toArray();
        $props = $arr['function']['parameters']['properties'];
        $this->assertArrayHasKey('unit', $props);
        $this->assertSame('string', $props['unit']['type']);
        $this->assertSame('celsius', $props['unit']['default']);
        $this->assertSame(['location'], $arr['function']['parameters']['required']);
    }

    public function testAllScalarTypes(): void
    {
        $tool = ReflectionTool::fromCallable(
            fn(string $a, int $b, float $c, bool $d): string => $a,
            name: 'test_types',
        );

        $props = $tool->toArray()['function']['parameters']['properties'];
        $this->assertSame('string', $props['a']['type']);
        $this->assertSame('integer', $props['b']['type']);
        $this->assertSame('number', $props['c']['type']);
        $this->assertSame('boolean', $props['d']['type']);
    }

    public function testArrayType(): void
    {
        $tool = ReflectionTool::fromCallable(
            fn(array $items): array => $items,
            name: 'test_array',
        );

        $props = $tool->toArray()['function']['parameters']['properties'];
        $this->assertSame('object', $props['items']['type']);
    }

    public function testNoTypeHintThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ReflectionTool::fromCallable(
            fn($untyped): string => '',
            name: 'bad_tool',
        );
    }

    public function testStrictFlag(): void
    {
        $tool = ReflectionTool::fromCallable(
            fn(string $x): string => $x,
            name: 'strict_tool',
            strict: true,
        );
        $this->assertTrue($tool->toArray()['function']['strict']);
    }

    public function testNoDescriptionOmitted(): void
    {
        $tool = ReflectionTool::fromCallable(
            fn(string $x): string => $x,
            name: 'no_desc',
        );
        $this->assertArrayNotHasKey('description', $tool->toArray()['function']);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit tests/Request/Tool/ReflectionToolTest.php`
Expected: FAIL — class does not exist

- [ ] **Step 3: Write ReflectionTool with core schema generation**

```php
<?php

namespace Knivey\OpenAi\Request\Tool;

use Knivey\OpenAi\Request\Tool\Attribute\ToolDescription;
use Knivey\OpenAi\Request\Tool\Attribute\ToolParam;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use BackedEnum;

class ReflectionTool implements ToolDefinition
{
    private readonly \ReflectionFunctionAbstract $reflection;
    private readonly \Closure $callable;

    private function __construct(
        \ReflectionFunctionAbstract $reflection,
        \Closure $callable,
        public readonly string $name,
        private readonly ?string $description = null,
        private readonly ?bool $strict = null,
    ) {
        $this->reflection = $reflection;
        $this->callable = $callable;
    }

    public static function fromCallable(
        callable $callable,
        ?string $name = null,
        ?string $description = null,
        ?bool $strict = null,
    ): self {
        $reflection = new ReflectionFunction($callable);

        $resolvedName = $name;
        if ($resolvedName === null) {
            $resolvedName = $reflection->getName();
        }
        if ($resolvedName === '{closure}' || $resolvedName === 'Closure') {
            throw new \InvalidArgumentException(
                'Closures require an explicit tool name via the $name parameter.',
            );
        }

        $resolvedDescription = $description;
        if ($resolvedDescription === null) {
            $resolvedDescription = self::readDescriptionAttribute($reflection);
        }

        return new self($reflection, \Closure::fromCallable($callable), $resolvedName, $resolvedDescription, $strict);
    }

    public static function fromMethod(
        array|string $method,
        ?string $name = null,
        ?string $description = null,
        ?bool $strict = null,
    ): self {
        if (is_string($method) && str_contains($method, '::')) {
            $reflection = new ReflectionMethod($method);
        } elseif (is_array($method)) {
            $reflection = new ReflectionMethod($method[0], $method[1]);
        } else {
            throw new \InvalidArgumentException(
                'Method must be [object, method] or "Class::method".',
            );
        }

        $resolvedName = $name ?? $reflection->getName();

        $resolvedDescription = $description;
        if ($resolvedDescription === null) {
            $resolvedDescription = self::readDescriptionAttribute($reflection);
        }

        if ($reflection->isStatic()) {
            $closure = \Closure::fromCallable($method);
        } else {
            $closure = $reflection->getClosure($method[0] ?? null);
        }

        return new self($reflection, $closure, $resolvedName, $resolvedDescription, $strict);
    }

    public function invoke(string $argumentsJson): mixed
    {
        $args = json_decode($argumentsJson, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($args)) {
            $args = [];
        }

        $namedArgs = [];
        foreach ($this->reflection->getParameters() as $param) {
            $paramName = $param->getName();
            if (array_key_exists($paramName, $args)) {
                $namedArgs[$paramName] = $args[$paramName];
            } elseif ($param->isDefaultValueAvailable()) {
                $namedArgs[$paramName] = $param->getDefaultValue();
            } else {
                throw new \InvalidArgumentException(
                    "Missing required parameter '{$paramName}' for tool '{$this->name}'.",
                );
            }
        }

        return ($this->callable)(...$namedArgs);
    }

    public function toArray(): array
    {
        $function = ['name' => $this->name];

        if ($this->description !== null) {
            $function['description'] = $this->description;
        }

        $function['parameters'] = $this->buildParametersSchema();

        if ($this->strict !== null) {
            $function['strict'] = $this->strict;
        }

        return ['type' => 'function', 'function' => $function];
    }

    private static function readDescriptionAttribute(\ReflectionFunctionAbstract $reflection): ?string
    {
        $attrs = $reflection->getAttributes(ToolDescription::class);
        if ($attrs !== []) {
            return $attrs[0]->newInstance()->description;
        }
        return null;
    }

    private static function readParamAttribute(ReflectionParameter $param): ?ToolParam
    {
        $attrs = $param->getAttributes(ToolParam::class);
        if ($attrs !== []) {
            return $attrs[0]->newInstance();
        }
        return null;
    }

    private function buildParametersSchema(): array
    {
        $properties = [];
        $required = [];

        foreach ($this->reflection->getParameters() as $param) {
            $type = $param->getType();
            if ($type === null) {
                throw new \InvalidArgumentException(
                    "Parameter '\${$param->getName()}' on tool '{$this->name}' has no type hint. All parameters must be typed.",
                );
            }
            if (!($type instanceof ReflectionNamedType) || $type->getName() === 'mixed') {
                throw new \InvalidArgumentException(
                    "Parameter '\${$param->getName()}' on tool '{$this->name}' must have a concrete type hint.",
                );
            }

            $prop = $this->buildPropertySchema($type, $param);
            $properties[$param->getName()] = $prop;

            if ($param->isDefaultValueAvailable()) {
                $prop['default'] = $param->getDefaultValue();
                $properties[$param->getName()] = $prop;
            } else {
                $required[] = $param->getName();
            }
        }

        $schema = ['type' => 'object', 'properties' => $properties];
        if ($required !== []) {
            $schema['required'] = $required;
        }
        return $schema;
    }

    private function buildPropertySchema(ReflectionNamedType $type, ReflectionParameter $param): array
    {
        $typeName = $type->getName();
        $attr = self::readParamAttribute($param);
        $prop = [];

        if ($attr !== null && $attr->type !== null) {
            $prop['type'] = $attr->type;
        } elseif (is_a($typeName, BackedEnum::class, true)) {
            $prop['type'] = (string) (new \ReflectionEnum($typeName))->getBackingType()->getName();
            if ($prop['type'] === 'int') {
                $prop['type'] = 'integer';
            }
        } else {
            $prop['type'] = match ($typeName) {
                'string' => 'string',
                'int' => 'integer',
                'float' => 'number',
                'bool' => 'boolean',
                'array' => 'object',
                default => throw new \InvalidArgumentException(
                    "Unsupported type '{$typeName}' for parameter '\${$param->getName()}' on tool '{$this->name}'.",
                ),
            };
        }

        if (is_a($typeName, BackedEnum::class, true)) {
            if ($attr !== null && $attr->enum !== null) {
                $prop['enum'] = $attr->enum;
            } else {
                $cases = [];
                foreach (($typeName::cases()) as $case) {
                    $cases[] = $case->value;
                }
                $prop['enum'] = $cases;
            }
        } elseif ($attr !== null && $attr->enum !== null) {
            $prop['enum'] = $attr->enum;
        }

        if ($attr !== null && $attr->description !== null) {
            $prop['description'] = $attr->description;
        }

        return $prop;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit tests/Request/Tool/ReflectionToolTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Request/Tool/ReflectionTool.php tests/Request/Tool/ReflectionToolTest.php
git commit -m "feat: add ReflectionTool with schema generation from type hints"
```

---

### Task 3: ReflectionTool — fromMethod and Attribute Tests

**Files:**
- Modify: `tests/Request/Tool/ReflectionToolTest.php`

- [ ] **Step 1: Write tests for fromMethod and attributes**

Append these tests to `tests/Request/Tool/ReflectionToolTest.php`:

```php
    public function testFromMethodWithInstance(): void
    {
        $obj = new class {
            #[ToolDescription('Test method')]
            public function my_tool(string $input): string
            {
                return $input;
            }
        };

        $tool = ReflectionTool::fromMethod([$obj, 'my_tool']);
        $arr = $tool->toArray();

        $this->assertSame('my_tool', $tool->name);
        $this->assertSame('Test method', $arr['function']['description']);
    }

    public function testFromMethodWithStaticMethod(): void
    {
        $tool = ReflectionTool::fromMethod(\Knivey\OpenAi\Tests\Request\Tool\Fixture\StaticToolFixture::class . '::static_tool');
        $this->assertSame('static_tool', $tool->name);
        $this->assertSame('A static tool', $tool->toArray()['function']['description']);
    }

    public function testToolParamDescriptionOverrides(): void
    {
        $obj = new class {
            public function param_tool(
                #[\Knivey\OpenAi\Request\Tool\Attribute\ToolParam(description: 'The city')]
                string $city,
            ): string {
                return $city;
            }
        };

        $tool = ReflectionTool::fromMethod([$obj, 'param_tool']);
        $props = $tool->toArray()['function']['parameters']['properties'];
        $this->assertSame('The city', $props['city']['description']);
    }

    public function testToolParamTypeEnumOverride(): void
    {
        $obj = new class {
            public function override_type(
                #[\Knivey\OpenAi\Request\Tool\Attribute\ToolParam(type: 'string')]
                int $count,
            ): string {
                return (string) $count;
            }
        };

        $tool = ReflectionTool::fromMethod([$obj, 'override_type']);
        $props = $tool->toArray()['function']['parameters']['properties'];
        $this->assertSame('string', $props['count']['type']);
    }

    public function testBackedStringEnum(): void
    {
        $tool = ReflectionTool::fromCallable(
            fn(\Knivey\OpenAi\Tests\Request\Tool\Fixture\StringUnit $unit = \Knivey\OpenAi\Tests\Request\Tool\Fixture\StringUnit::Celsius): string => $unit->value,
            name: 'enum_test',
        );

        $props = $tool->toArray()['function']['parameters']['properties'];
        $this->assertSame('string', $props['unit']['type']);
        $this->assertSame(['celsius', 'fahrenheit'], $props['unit']['enum']);
        $this->assertSame('celsius', $props['unit']['default']);
        $this->assertSame([], $tool->toArray()['function']['parameters']['required']);
    }

    public function testBackedIntEnum(): void
    {
        $tool = ReflectionTool::fromCallable(
            fn(\Knivey\OpenAi\Tests\Request\Tool\Fixture\IntPriority $priority): int => $priority->value,
            name: 'enum_int_test',
        );

        $props = $tool->toArray()['function']['parameters']['properties'];
        $this->assertSame('integer', $props['priority']['type']);
        $this->assertSame([1, 2, 3], $props['priority']['enum']);
        $this->assertSame(['priority'], $tool->toArray()['function']['parameters']['required']);
    }

    public function testInvokeWithValidJson(): void
    {
        $tool = ReflectionTool::fromCallable(
            fn(string $location, string $unit = 'celsius'): string => "{$location}:{$unit}",
            name: 'invoke_test',
        );

        $result = $tool->invoke('{"location":"SF"}');
        $this->assertSame('SF:celsius', $result);
    }

    public function testInvokeWithAllArgs(): void
    {
        $tool = ReflectionTool::fromCallable(
            fn(string $location, string $unit = 'celsius'): string => "{$location}:{$unit}",
            name: 'invoke_test',
        );

        $result = $tool->invoke('{"location":"NYC","unit":"fahrenheit"}');
        $this->assertSame('NYC:fahrenheit', $result);
    }

    public function testInvokeMissingRequiredThrows(): void
    {
        $tool = ReflectionTool::fromCallable(
            fn(string $location): string => $location,
            name: 'invoke_test',
        );

        $this->expectException(\InvalidArgumentException::class);
        $tool->invoke('{}');
    }

    public function testInvokeInvalidJsonThrows(): void
    {
        $tool = ReflectionTool::fromCallable(
            fn(string $x): string => $x,
            name: 'invoke_test',
        );

        $this->expectException(\JsonException::class);
        $tool->invoke('not json');
    }

    public function testFromMethodWithNameOverride(): void
    {
        $obj = new class {
            public function default_name(string $x): string
            {
                return $x;
            }
        };

        $tool = ReflectionTool::fromMethod([$obj, 'default_name'], name: 'custom_name');
        $this->assertSame('custom_name', $tool->name);
    }

    public function testDescriptionExplicitOverridesAttribute(): void
    {
        $obj = new class {
            #[ToolDescription('From attribute')]
            public function my_tool(string $x): string
            {
                return $x;
            }
        };

        $tool = ReflectionTool::fromMethod([$obj, 'my_tool'], description: 'Explicit wins');
        $this->assertSame('Explicit wins', $tool->toArray()['function']['description']);
    }
}
```

- [ ] **Step 2: Create test fixtures for enums and static method**

Create `tests/Request/Tool/Fixture/StringUnit.php`:

```php
<?php

namespace Knivey\OpenAi\Tests\Request\Tool\Fixture;

enum StringUnit: string
{
    case Celsius = 'celsius';
    case Fahrenheit = 'fahrenheit';
}
```

Create `tests/Request/Tool/Fixture/IntPriority.php`:

```php
<?php

namespace Knivey\OpenAi\Tests\Request\Tool\Fixture;

enum IntPriority: int
{
    case Low = 1;
    case Medium = 2;
    case High = 3;
}
```

Create `tests/Request/Tool/Fixture/StaticToolFixture.php`:

```php
<?php

namespace Knivey\OpenAi\Tests\Request\Tool\Fixture;

use Knivey\OpenAi\Request\Tool\Attribute\ToolDescription;

class StaticToolFixture
{
    #[ToolDescription('A static tool')]
    public static function static_tool(string $input): string
    {
        return $input;
    }
}
```

- [ ] **Step 3: Run all ReflectionTool tests**

Run: `vendor/bin/phpunit tests/Request/Tool/ReflectionToolTest.php`
Expected: ALL PASS

- [ ] **Step 4: Commit**

```bash
git add tests/Request/Tool/ReflectionToolTest.php tests/Request/Tool/Fixture/
git commit -m "test: add ReflectionTool tests for fromMethod, attributes, enums, invoke"
```

---

### Task 4: ToolRegistry

**Files:**
- Create: `src/Request/Tool/ToolRegistry.php`
- Create: `tests/Request/Tool/ToolRegistryTest.php`

- [ ] **Step 1: Write tests for ToolRegistry**

```php
<?php

namespace Knivey\OpenAi\Tests\Request\Tool;

use Knivey\OpenAi\Request\Tool\ReflectionTool;
use Knivey\OpenAi\Request\Tool\ToolRegistry;
use Knivey\OpenAi\Response\ToolCall;
use PHPUnit\Framework\TestCase;

class ToolRegistryTest extends TestCase
{
    private function makeTool(string $name, ?string $description = null): ReflectionTool
    {
        return ReflectionTool::fromCallable(
            fn(string $input): string => "result:{$input}",
            name: $name,
            description: $description,
        );
    }

    public function testCreateReturnsInstance(): void
    {
        $registry = ToolRegistry::create();
        $this->assertInstanceOf(ToolRegistry::class, $registry);
    }

    public function testAddAndGetTool(): void
    {
        $tool = $this->makeTool('my_tool', 'Does things');
        $registry = ToolRegistry::create()->add($tool);

        $this->assertTrue($registry->has('my_tool'));
        $this->assertSame($tool, $registry->get('my_tool'));
    }

    public function testHasReturnsFalseForMissing(): void
    {
        $registry = ToolRegistry::create();
        $this->assertFalse($registry->has('missing'));
    }

    public function testGetThrowsForMissing(): void
    {
        $registry = ToolRegistry::create();
        $this->expectException(\InvalidArgumentException::class);
        $registry->get('missing');
    }

    public function testGetToolsReturnsToolDefinitions(): void
    {
        $tool = $this->makeTool('t1');
        $registry = ToolRegistry::create()->add($tool);

        $tools = $registry->getTools();
        $this->assertCount(1, $tools);
        $this->assertSame($tool, $tools[0]);
    }

    public function testFluentAdd(): void
    {
        $registry = ToolRegistry::create()
            ->add($this->makeTool('a'))
            ->add($this->makeTool('b'));

        $this->assertTrue($registry->has('a'));
        $this->assertTrue($registry->has('b'));
        $this->assertCount(2, $registry->getTools());
    }

    public function testDispatchCallsTool(): void
    {
        $registry = ToolRegistry::create()->add($this->makeTool('echo'));
        $result = $registry->dispatch('echo', '{"input":"hello"}');
        $this->assertSame('result:hello', $result);
    }

    public function testDispatchUnknownThrows(): void
    {
        $registry = ToolRegistry::create();
        $this->expectException(\InvalidArgumentException::class);
        $registry->dispatch('unknown', '{}');
    }

    public function testDispatchAll(): void
    {
        $registry = ToolRegistry::create()->add($this->makeTool('echo'));

        $toolCalls = [
            ToolCall::fromApiResponse([
                'id' => 'call_1',
                'type' => 'function',
                'function' => ['name' => 'echo', 'arguments' => '{"input":"a"}'],
            ]),
            ToolCall::fromApiResponse([
                'id' => 'call_2',
                'type' => 'function',
                'function' => ['name' => 'echo', 'arguments' => '{"input":"b"}'],
            ]),
        ];

        $results = $registry->dispatchAll($toolCalls);
        $this->assertSame('result:a', $results['call_1']);
        $this->assertSame('result:b', $results['call_2']);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit tests/Request/Tool/ToolRegistryTest.php`
Expected: FAIL — class does not exist

- [ ] **Step 3: Write ToolRegistry**

```php
<?php

namespace Knivey\OpenAi\Request\Tool;

use Knivey\OpenAi\Response\ToolCall;

class ToolRegistry
{
    /** @var array<string, ReflectionTool> */
    private array $tools = [];

    public static function create(): self
    {
        return new self();
    }

    public function add(ReflectionTool $tool): self
    {
        $this->tools[$tool->name] = $tool;
        return $this;
    }

    public function get(string $name): ReflectionTool
    {
        if (!isset($this->tools[$name])) {
            throw new \InvalidArgumentException("Tool '{$name}' not found in registry.");
        }
        return $this->tools[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    public function getTools(): array
    {
        return array_values($this->tools);
    }

    public function dispatch(string $name, string $argumentsJson): mixed
    {
        return $this->get($name)->invoke($argumentsJson);
    }

    /**
     * @param list<ToolCall> $toolCalls
     * @return array<string, mixed>
     */
    public function dispatchAll(array $toolCalls): array
    {
        $results = [];
        foreach ($toolCalls as $toolCall) {
            if ($toolCall->function === null) {
                continue;
            }
            $results[$toolCall->id] = $this->dispatch(
                $toolCall->function['name'],
                $toolCall->function['arguments'],
            );
        }
        return $results;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit tests/Request/Tool/ToolRegistryTest.php`
Expected: ALL PASS

- [ ] **Step 5: Commit**

```bash
git add src/Request/Tool/ToolRegistry.php tests/Request/Tool/ToolRegistryTest.php
git commit -m "feat: add ToolRegistry for dispatching tool calls"
```

---

### Task 5: Client Integration — chatCompletionWithTools

**Files:**
- Modify: `src/OpenAiClient.php`
- Modify: `tests/OpenAiClientTest.php`

- [ ] **Step 1: Write test for chatCompletionWithTools**

Append to `tests/OpenAiClientTest.php`:

```php
    public function testChatCompletionWithToolsRunsFullLoop(): void
    {
        $callCount = 0;
        $toolCallResponse = [
            'id' => 'chatcmpl-1',
            'object' => 'chat.completion',
            'created' => 1234567890,
            'model' => 'gpt-4',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [
                            [
                                'id' => 'call_1',
                                'type' => 'function',
                                'function' => ['name' => 'echo', 'arguments' => '{"input":"hello"}'],
                            ],
                        ],
                    ],
                    'finish_reason' => 'tool_calls',
                ],
            ],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
        ];
        $finalResponse = [
            'id' => 'chatcmpl-2',
            'object' => 'chat.completion',
            'created' => 1234567890,
            'model' => 'gpt-4',
            'choices' => [
                [
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => 'The echo result is: result:hello'],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => ['prompt_tokens' => 20, 'completion_tokens' => 10, 'total_tokens' => 30],
        ];

        $mock = new class ($callCount, $toolCallResponse, $finalResponse) implements DelegateHttpClient {
            public int $callCount = 0;

            /** @param array<string, mixed> $toolCallResponse */
            public function __construct(
                int $callCount,
                private readonly array $toolCallResponse,
                private readonly array $finalResponse,
            ) {
                $this->callCount = $callCount;
            }

            public function request(HttpRequest $request, \Amp\Cancellation $cancellation): Response
            {
                $this->callCount++;
                $data = $this->callCount === 1
                    ? $this->toolCallResponse
                    : $this->finalResponse;
                $body = (string) json_encode($data);
                $dummyRequest = new HttpRequest('https://api.openai.com/v1/test');

                return new Response('1.1', 200, '', [], $body, $dummyRequest);
            }
        };

        $client = new OpenAiClient('test-key', httpClient: new \Knivey\OpenAi\HttpClient('test-key', $mock));

        $registry = \Knivey\OpenAi\Request\Tool\ToolRegistry::create()
            ->add(\Knivey\OpenAi\Request\Tool\ReflectionTool::fromCallable(
                fn(string $input): string => "result:{$input}",
                name: 'echo',
            ));

        $response = $client->chatCompletionWithTools(
            new ChatRequest(model: 'gpt-4', messages: [Message::user('test')], tools: $registry->getTools()),
            $registry,
        );

        $this->assertSame('The echo result is: result:hello', $response->choices[0]->message->content);
        $this->assertSame(2, $mock->callCount);
    }

    public function testChatCompletionWithToolsMaxIterationsThrows(): void
    {
        $infiniteToolCallResponse = [
            'id' => 'chatcmpl-1',
            'object' => 'chat.completion',
            'created' => 1234567890,
            'model' => 'gpt-4',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [
                            [
                                'id' => 'call_loop',
                                'type' => 'function',
                                'function' => ['name' => 'echo', 'arguments' => '{"input":"loop"}'],
                            ],
                        ],
                    ],
                    'finish_reason' => 'tool_calls',
                ],
            ],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
        ];

        $mock = new class ($infiniteToolCallResponse) implements DelegateHttpClient {
            /** @param array<string, mixed> $response */
            public function __construct(private readonly array $response) {}

            public function request(HttpRequest $request, \Amp\Cancellation $cancellation): Response
            {
                $body = (string) json_encode($this->response);
                $dummyRequest = new HttpRequest('https://api.openai.com/v1/test');

                return new Response('1.1', 200, '', [], $body, $dummyRequest);
            }
        };

        $client = new OpenAiClient('test-key', httpClient: new \Knivey\OpenAi\HttpClient('test-key', $mock));

        $registry = \Knivey\OpenAi\Request\Tool\ToolRegistry::create()
            ->add(\Knivey\OpenAi\Request\Tool\ReflectionTool::fromCallable(
                fn(string $input): string => "result:{$input}",
                name: 'echo',
            ));

        $this->expectException(\RuntimeException::class);
        $client->chatCompletionWithTools(
            new ChatRequest(model: 'gpt-4', messages: [Message::user('test')], tools: $registry->getTools()),
            $registry,
            maxIterations: 2,
        );
    }
```

- [ ] **Step 2: Add the required use statements to OpenAiClientTest**

Add at the top of `tests/OpenAiClientTest.php` after existing use statements:

```php
use Knivey\OpenAi\Request\Tool\ToolRegistry;
use Knivey\OpenAi\Request\Tool\ReflectionTool;
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `vendor/bin/phpunit tests/OpenAiClientTest.php --filter chatCompletionWithTools`
Expected: FAIL — method does not exist

- [ ] **Step 4: Add chatCompletionWithTools to OpenAiClient**

Add to `src/OpenAiClient.php` after `chatCompletion()`. Add the necessary use statements at the top of the file:

```php
use Knivey\OpenAi\Request\Tool\ToolRegistry;
```

Then add the method:

```php
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

            $currentRequest = new ChatRequest(
                model: $request->model,
                messages: $messages,
                temperature: $request->temperature,
                topP: $request->topP,
                maxTokens: $request->maxTokens,
                maxCompletionTokens: $request->maxCompletionTokens,
                n: $request->n,
                stop: $request->stop,
                stream: $request->stream,
                streamOptions: $request->streamOptions,
                frequencyPenalty: $request->frequencyPenalty,
                presencePenalty: $request->presencePenalty,
                seed: $request->seed,
                logprobs: $request->logprobs,
                topLogprobs: $request->topLogprobs,
                logitBias: $request->logitBias,
                user: $request->user,
                store: $request->store,
                metadata: $request->metadata,
                serviceTier: $request->serviceTier,
                tools: $request->tools,
                toolChoice: $request->toolChoice,
                parallelToolCalls: $request->parallelToolCalls,
                responseFormat: $request->responseFormat,
                modalities: $request->modalities,
                audio: $request->audio,
                prediction: $request->prediction,
                reasoningEffort: $request->reasoningEffort,
                webSearch: $request->webSearch,
                moderation: $request->moderation,
            );

            $response = $this->chatCompletion($currentRequest);

            $toolCalls = $response->choices[0]->message->toolCalls;
            if ($toolCalls === null || $toolCalls === []) {
                return $response;
            }

            $assistantMessage = $response->choices[0]->message;
            $messages[] = new Message(
                role: 'assistant',
                content: $assistantMessage->content,
                toolCalls: array_map(static fn ($tc) => [
                    'id' => $tc->id,
                    'type' => $tc->type,
                    'function' => $tc->function,
                ], $assistantMessage->toolCalls ?? []),
                refusal: $assistantMessage->refusal,
            );

            $results = $registry->dispatchAll($toolCalls);
            foreach ($toolCalls as $toolCall) {
                $messages[] = Message::tool(
                    (string) $results[$toolCall->id],
                    $toolCall->id,
                );
            }
        }
    }
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/phpunit tests/OpenAiClientTest.php --filter chatCompletionWithTools`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add src/OpenAiClient.php tests/OpenAiClientTest.php
git commit -m "feat: add chatCompletionWithTools for automatic tool call loop"
```

---

### Task 6: Run Full Test Suite and Static Analysis

**Files:**
- All files

- [ ] **Step 1: Run all tests**

Run: `vendor/bin/phpunit`
Expected: ALL PASS

- [ ] **Step 2: Run PHPStan**

Run: `vendor/bin/phpstan analyse`
Expected: No errors. Fix any type issues.

- [ ] **Step 3: Run CS check**

Run: `vendor/bin/php-cs-fixer check`
Expected: No issues. Fix any style issues with `vendor/bin/php-cs-fixer fix` if needed.

- [ ] **Step 4: Commit any fixes**

```bash
git add -A
git commit -m "style: fix cs and stan issues"
```
