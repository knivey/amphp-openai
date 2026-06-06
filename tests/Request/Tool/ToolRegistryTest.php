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
            fn (string $input): string => "result:{$input}",
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
