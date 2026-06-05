<?php

namespace Knivey\OpenAi\Tests\Request\Content;

use Knivey\OpenAi\Request\Content\TextPart;
use PHPUnit\Framework\TestCase;

class TextPartTest extends TestCase
{
    public function testToArrayReturnsTextPart(): void
    {
        $part = new TextPart('hello');
        $this->assertSame(['type' => 'text', 'text' => 'hello'], $part->toArray());
    }
}
