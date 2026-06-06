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
