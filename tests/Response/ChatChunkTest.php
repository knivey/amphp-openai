<?php

namespace Knivey\OpenAi\Tests\Response;

use Knivey\OpenAi\Response\ChatChunk;
use PHPUnit\Framework\TestCase;

class ChatChunkTest extends TestCase
{
    public function testFromApiResponse(): void
    {
        $chunk = ChatChunk::fromApiResponse([
            'id' => 'chatcmpl-123',
            'object' => 'chat.completion.chunk',
            'created' => 1234567890,
            'model' => 'gpt-4',
            'choices' => [
                [
                    'index' => 0,
                    'delta' => ['role' => 'assistant', 'content' => 'Hello'],
                    'finish_reason' => null,
                ],
            ],
        ]);
        $this->assertSame('chatcmpl-123', $chunk->id);
        $this->assertCount(1, $chunk->choices);
        $this->assertSame('Hello', $chunk->choices[0]->delta['content']);
        $this->assertNull($chunk->choices[0]->finishReason);
    }

    public function testChunkWithUsage(): void
    {
        $chunk = ChatChunk::fromApiResponse([
            'id' => 'chatcmpl-123',
            'object' => 'chat.completion.chunk',
            'created' => 1234567890,
            'model' => 'gpt-4',
            'choices' => [],
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 5,
                'total_tokens' => 15,
            ],
        ]);
        $this->assertNotNull($chunk->usage);
        $this->assertSame(15, $chunk->usage->totalTokens);
    }
}
