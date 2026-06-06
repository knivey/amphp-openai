<?php

namespace Knivey\OpenAi\Tests\Request;

use Knivey\OpenAi\Request\Reasoning;
use PHPUnit\Framework\TestCase;

class ReasoningTest extends TestCase
{
    public function testEffortNamedConstructor(): void
    {
        $r = Reasoning::effort('high');
        $this->assertSame('high', $r->effort);
        $this->assertNull($r->maxTokens);
        $this->assertNull($r->exclude);
        $this->assertNull($r->enabled);
    }

    public function testMaxTokensNamedConstructor(): void
    {
        $r = Reasoning::maxTokens(2000);
        $this->assertNull($r->effort);
        $this->assertSame(2000, $r->maxTokens);
    }

    public function testEnabledNamedConstructor(): void
    {
        $r = Reasoning::enabled();
        $this->assertTrue($r->enabled);
        $this->assertNull($r->effort);
    }

    public function testConstructorWithAllFields(): void
    {
        $r = new Reasoning(
            effort: 'high',
            maxTokens: 8000,
            exclude: true,
            enabled: true,
        );
        $this->assertSame('high', $r->effort);
        $this->assertSame(8000, $r->maxTokens);
        $this->assertTrue($r->exclude);
        $this->assertTrue($r->enabled);
    }

    public function testToArrayOnlyIncludesNonNullFields(): void
    {
        $r = Reasoning::effort('low');
        $arr = $r->toArray();
        $this->assertSame(['effort' => 'low'], $arr);
    }

    public function testToArrayWithMultipleFields(): void
    {
        $r = new Reasoning(effort: 'high', exclude: true);
        $arr = $r->toArray();
        $this->assertSame(['effort' => 'high', 'exclude' => true], $arr);
    }

    public function testToArrayWithAllFields(): void
    {
        $r = new Reasoning(effort: 'medium', maxTokens: 5000, exclude: false, enabled: true);
        $arr = $r->toArray();
        $this->assertSame([
            'effort' => 'medium',
            'max_tokens' => 5000,
            'exclude' => false,
            'enabled' => true,
        ], $arr);
    }

    public function testToArrayWithOnlyMaxTokens(): void
    {
        $r = Reasoning::maxTokens(1024);
        $arr = $r->toArray();
        $this->assertSame(['max_tokens' => 1024], $arr);
    }
}
