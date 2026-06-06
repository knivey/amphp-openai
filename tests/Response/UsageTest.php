<?php

namespace Knivey\OpenAi\Tests\Response;

use Knivey\OpenAi\Response\Usage;
use PHPUnit\Framework\TestCase;

class UsageTest extends TestCase
{
    public function testFromApiResponse(): void
    {
        $usage = Usage::fromApiResponse([
            'prompt_tokens' => 10,
            'completion_tokens' => 20,
            'total_tokens' => 30,
            'completion_tokens_details' => ['reasoning_tokens' => 5],
            'prompt_tokens_details' => ['cached_tokens' => 3],
        ]);
        $this->assertSame(10, $usage->promptTokens);
        $this->assertSame(20, $usage->completionTokens);
        $this->assertSame(30, $usage->totalTokens);
        $this->assertSame(['reasoning_tokens' => 5], $usage->completionTokensDetails);
        $this->assertSame(['cached_tokens' => 3], $usage->promptTokensDetails);
    }

    public function testFromApiResponseMinimal(): void
    {
        $usage = Usage::fromApiResponse([
            'prompt_tokens' => 5,
            'completion_tokens' => 10,
            'total_tokens' => 15,
        ]);
        $this->assertNull($usage->completionTokensDetails);
        $this->assertNull($usage->promptTokensDetails);
    }
}
