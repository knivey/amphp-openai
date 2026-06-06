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
        $this->assertSame('Get weather', $fn['description']);
        $this->assertSame('object', $fn['parameters']['type']);
        $this->assertArrayHasKey('location', $fn['parameters']['properties']);
        $this->assertSame('string', $fn['parameters']['properties']['location']['type']);
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
        $this->assertSame('celsius', $props['unit']['default']);
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
        $this->assertTrue($tool->toArray()['function']['strict']);
    }

    public function testNoDescriptionOmitted(): void
    {
        $tool = ReflectionTool::fromCallable(
            fn (string $x): string => $x,
            name: 'no_desc',
        );
        $this->assertArrayNotHasKey('description', $tool->toArray()['function']);
    }
}
