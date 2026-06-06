<?php

namespace Knivey\OpenAi\Tests\Response;

use Knivey\OpenAi\Response\ToolCall;
use PHPUnit\Framework\TestCase;

class ToolCallTest extends TestCase
{
    public function testFunctionToolCall(): void
    {
        $tc = ToolCall::fromApiResponse([
            'id' => 'call_123',
            'type' => 'function',
            'function' => ['name' => 'get_weather', 'arguments' => '{"location":"SF"}'],
        ]);
        $this->assertSame('call_123', $tc->id);
        $this->assertSame('function', $tc->type);
        $this->assertNotNull($tc->function);
        $this->assertSame('get_weather', $tc->function['name']);
        $this->assertNull($tc->custom);
    }

    public function testCustomToolCall(): void
    {
        $tc = ToolCall::fromApiResponse([
            'id' => 'call_456',
            'type' => 'custom',
            'custom' => ['name' => 'my_tool', 'input' => 'some input'],
        ]);
        $this->assertSame('custom', $tc->type);
        $this->assertNotNull($tc->custom);
        $this->assertSame('my_tool', $tc->custom['name']);
        $this->assertNull($tc->function);
    }
}
