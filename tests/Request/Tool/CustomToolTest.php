<?php

namespace Knivey\OpenAi\Tests\Request\Tool;

use Knivey\OpenAi\Request\Tool\CustomTool;
use PHPUnit\Framework\TestCase;

class CustomToolTest extends TestCase
{
    public function testMinimalCustomTool(): void
    {
        $tool = new CustomTool('my_tool');
        $this->assertSame(
            ['type' => 'custom', 'custom' => ['name' => 'my_tool']],
            $tool->toArray(),
        );
    }

    public function testCustomToolWithDescription(): void
    {
        $tool = new CustomTool('my_tool', description: 'Does a thing');
        $arr = $tool->toArray();
        $custom = $arr['custom'];
        $this->assertIsArray($custom);
        $this->assertSame('Does a thing', $custom['description']);
    }

    public function testCustomToolWithGrammarFormat(): void
    {
        $format = ['type' => 'grammar', 'grammar' => ['definition' => 'rule', 'syntax' => 'lark']];
        $tool = new CustomTool('my_tool', format: $format);
        $arr = $tool->toArray();
        $custom = $arr['custom'];
        $this->assertIsArray($custom);
        $this->assertSame($format, $custom['format']);
    }
}
