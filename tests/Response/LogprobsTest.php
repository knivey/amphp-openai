<?php

namespace Knivey\OpenAi\Tests\Response;

use Knivey\OpenAi\Response\Logprobs;
use PHPUnit\Framework\TestCase;

class LogprobsTest extends TestCase
{
    public function testFromApiResponse(): void
    {
        $logprobs = Logprobs::fromApiResponse([
            'content' => [
                ['token' => 'hello', 'logprob' => -0.5, 'top_logprobs' => []],
            ],
            'refusal' => null,
        ]);
        $this->assertNotNull($logprobs);
        $this->assertNotNull($logprobs->content);
        $this->assertCount(1, $logprobs->content);
        $this->assertSame('hello', $logprobs->content[0]->token);
        $this->assertNull($logprobs->refusal);
    }

    public function testFromApiResponseNull(): void
    {
        $logprobs = Logprobs::fromApiResponse(null);
        $this->assertNull($logprobs);
    }
}
