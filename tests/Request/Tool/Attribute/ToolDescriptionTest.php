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
