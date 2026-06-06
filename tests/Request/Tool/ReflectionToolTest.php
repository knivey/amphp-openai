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
            fn (string $location): string => $location,
            name: 'get_weather',
            description: 'Get weather',
        );

        $this->assertSame('get_weather', $tool->name);
        $arr = $tool->toArray();
        $this->assertSame('function', $arr['type']);
        $fn = $arr['function'];
        $this->assertSame('get_weather', $fn['name']);
        assert(array_key_exists('description', $fn));
        $this->assertSame('Get weather', $fn['description']);
        $this->assertSame('object', $fn['parameters']['type']);
        $this->assertArrayHasKey('location', $fn['parameters']['properties']);
        $this->assertSame('string', $fn['parameters']['properties']['location']['type']);
        assert(array_key_exists('required', $fn['parameters']));
        $this->assertSame(['location'], $fn['parameters']['required']);
    }

    public function testClosureWithoutNameThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ReflectionTool::fromCallable(fn (string $x): string => $x);
    }

    public function testOptionalParameterWithDefault(): void
    {
        $tool = ReflectionTool::fromCallable(
            fn (string $location, string $unit = 'celsius'): string => $location,
            name: 'get_weather',
        );

        $arr = $tool->toArray();
        $props = $arr['function']['parameters']['properties'];
        $this->assertArrayHasKey('unit', $props);
        $this->assertSame('string', $props['unit']['type']);
        assert(array_key_exists('default', $props['unit']));
        $this->assertSame('celsius', $props['unit']['default']);
        assert(array_key_exists('required', $arr['function']['parameters']));
        $this->assertSame(['location'], $arr['function']['parameters']['required']);
    }

    public function testAllScalarTypes(): void
    {
        $tool = ReflectionTool::fromCallable(
            fn (string $a, int $b, float $c, bool $d): string => $a,
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
            fn (array $items): array => $items,
            name: 'test_array',
        );

        $props = $tool->toArray()['function']['parameters']['properties'];
        $this->assertSame('object', $props['items']['type']);
    }

    public function testNoTypeHintThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ReflectionTool::fromCallable(
            fn ($untyped): string => '',
            name: 'bad_tool',
        );
    }

    public function testStrictFlag(): void
    {
        $tool = ReflectionTool::fromCallable(
            fn (string $x): string => $x,
            name: 'strict_tool',
            strict: true,
        );
        $fn = $tool->toArray()['function'];
        assert(array_key_exists('strict', $fn));
        $this->assertTrue($fn['strict']);
    }

    public function testNoDescriptionOmitted(): void
    {
        $tool = ReflectionTool::fromCallable(
            fn (string $x): string => $x,
            name: 'no_desc',
        );
        $this->assertArrayNotHasKey('description', $tool->toArray()['function']);
    }

    public function testFromMethodWithInstance(): void
    {
        $obj = new class () {
            #[ToolDescription('Test method')]
            public function my_tool(string $input): string
            {
                return $input;
            }
        };

        $tool = ReflectionTool::fromMethod([$obj, 'my_tool']);
        $arr = $tool->toArray();

        $this->assertSame('my_tool', $tool->name);
        assert(array_key_exists('description', $arr['function']));
        $this->assertSame('Test method', $arr['function']['description']);
    }

    public function testFromMethodWithStaticMethod(): void
    {
        $tool = ReflectionTool::fromMethod(\Knivey\OpenAi\Tests\Request\Tool\Fixture\StaticToolFixture::class . '::static_tool');
        $this->assertSame('static_tool', $tool->name);
        $fn = $tool->toArray()['function'];
        assert(array_key_exists('description', $fn));
        $this->assertSame('A static tool', $fn['description']);
    }

    public function testToolParamDescriptionOverrides(): void
    {
        $obj = new class () {
            public function param_tool(
                #[\Knivey\OpenAi\Request\Tool\Attribute\ToolParam(description: 'The city')]
                string $city,
            ): string {
                return $city;
            }
        };

        $tool = ReflectionTool::fromMethod([$obj, 'param_tool']);
        $props = $tool->toArray()['function']['parameters']['properties'];
        assert(array_key_exists('description', $props['city']));
        $this->assertSame('The city', $props['city']['description']);
    }

    public function testToolParamTypeEnumOverride(): void
    {
        $obj = new class () {
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
            fn (\Knivey\OpenAi\Tests\Request\Tool\Fixture\StringUnit $unit = \Knivey\OpenAi\Tests\Request\Tool\Fixture\StringUnit::Celsius): string => $unit->value,
            name: 'enum_test',
        );

        $arr = $tool->toArray();
        $props = $arr['function']['parameters']['properties'];
        $this->assertSame('string', $props['unit']['type']);
        assert(array_key_exists('enum', $props['unit']));
        $this->assertSame(['celsius', 'fahrenheit'], $props['unit']['enum']);
        assert(array_key_exists('default', $props['unit']));
        $this->assertSame('celsius', $props['unit']['default']);
        assert(array_key_exists('required', $arr['function']['parameters']));
        $this->assertSame([], $arr['function']['parameters']['required']);
    }

    public function testBackedIntEnum(): void
    {
        $tool = ReflectionTool::fromCallable(
            fn (\Knivey\OpenAi\Tests\Request\Tool\Fixture\IntPriority $priority): int => $priority->value,
            name: 'enum_int_test',
        );

        $props = $tool->toArray()['function']['parameters']['properties'];
        $this->assertSame('integer', $props['priority']['type']);
        assert(array_key_exists('enum', $props['priority']));
        $this->assertSame([1, 2, 3], $props['priority']['enum']);
        $fn = $tool->toArray()['function'];
        assert(array_key_exists('required', $fn['parameters']));
        $this->assertSame(['priority'], $fn['parameters']['required']);
    }

    public function testInvokeWithValidJson(): void
    {
        $tool = ReflectionTool::fromCallable(
            fn (string $location, string $unit = 'celsius'): string => "{$location}:{$unit}",
            name: 'invoke_test',
        );

        $result = $tool->invoke('{"location":"SF"}');
        $this->assertSame('SF:celsius', $result);
    }

    public function testInvokeWithAllArgs(): void
    {
        $tool = ReflectionTool::fromCallable(
            fn (string $location, string $unit = 'celsius'): string => "{$location}:{$unit}",
            name: 'invoke_test',
        );

        $result = $tool->invoke('{"location":"NYC","unit":"fahrenheit"}');
        $this->assertSame('NYC:fahrenheit', $result);
    }

    public function testInvokeMissingRequiredThrows(): void
    {
        $tool = ReflectionTool::fromCallable(
            fn (string $location): string => $location,
            name: 'invoke_test',
        );

        $this->expectException(\InvalidArgumentException::class);
        $tool->invoke('{}');
    }

    public function testInvokeInvalidJsonThrows(): void
    {
        $tool = ReflectionTool::fromCallable(
            fn (string $x): string => $x,
            name: 'invoke_test',
        );

        $this->expectException(\JsonException::class);
        $tool->invoke('not json');
    }

    public function testFromMethodWithNameOverride(): void
    {
        $obj = new class () {
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
        $obj = new class () {
            #[ToolDescription('From attribute')]
            public function my_tool(string $x): string
            {
                return $x;
            }
        };

        $tool = ReflectionTool::fromMethod([$obj, 'my_tool'], description: 'Explicit wins');
        $fn = $tool->toArray()['function'];
        assert(array_key_exists('description', $fn));
        $this->assertSame('Explicit wins', $fn['description']);
    }
}
