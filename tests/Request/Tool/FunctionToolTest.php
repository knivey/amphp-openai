<?php

namespace Knivey\OpenAi\Tests\Request\Tool;

use Knivey\OpenAi\Request\Tool\FunctionTool;
use PHPUnit\Framework\TestCase;

class FunctionToolTest extends TestCase
{
    public function testMinimalFunctionTool(): void
    {
        $tool = new FunctionTool('get_weather');
        $this->assertSame(
            ['type' => 'function', 'function' => ['name' => 'get_weather']],
            $tool->toArray(),
        );
    }

    public function testFullFunctionTool(): void
    {
        $tool = new FunctionTool(
            'get_weather',
            description: 'Get current weather',
            parameters: ['type' => 'object', 'properties' => ['location' => ['type' => 'string']]],
            strict: true,
        );
        $arr = $tool->toArray();
        $this->assertSame('function', $arr['type']);
        $function = $arr['function'];
        $this->assertIsArray($function);
        $this->assertSame('get_weather', $function['name']);
        $this->assertSame('Get current weather', $function['description']);
        $parameters = $function['parameters'];
        $this->assertIsArray($parameters);
        $this->assertArrayHasKey('properties', $parameters);
        $this->assertTrue($function['strict']);
    }
}
