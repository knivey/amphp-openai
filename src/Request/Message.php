<?php

namespace Knivey\OpenAi\Request;

use Knivey\OpenAi\Request\Content\ContentPart;

readonly class Message
{
    /**
     * @param string|array<ContentPart>|null $content
     * @param array<string, mixed>|null $functionCall
     * @param array<string, mixed>|null $audio
     * @param array<int, array<string, mixed>>|null $toolCalls
     */
    public function __construct(
        public string $role,
        public string|array|null $content = null,
        public ?string $name = null,
        public ?string $refusal = null,
        public ?string $toolCallId = null,
        public ?array $functionCall = null,
        public ?array $audio = null,
        public ?array $toolCalls = null,
    ) {
    }

    public static function developer(string $content): self
    {
        return new self(role: 'developer', content: $content);
    }

    public static function system(string $content): self
    {
        return new self(role: 'system', content: $content);
    }

    /**
     * @param string|array<int, ContentPart> $content
     */
    public static function user(string|array $content): self
    {
        return new self(role: 'user', content: $content);
    }

    /**
     * @param array<int, array<string, mixed>>|null $toolCalls
     */
    public static function assistant(?string $content = null, ?array $toolCalls = null, ?string $refusal = null): self
    {
        return new self(role: 'assistant', content: $content, toolCalls: $toolCalls, refusal: $refusal);
    }

    public static function tool(string $content, string $toolCallId): self
    {
        return new self(role: 'tool', content: $content, toolCallId: $toolCallId);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = ['role' => $this->role];

        if (is_array($this->content)) {
            $result['content'] = array_map(
                static fn (ContentPart $part): array => $part->toArray(),
                $this->content,
            );
        } else {
            $result['content'] = $this->content;
        }

        if ($this->name !== null) {
            $result['name'] = $this->name;
        }

        if ($this->refusal !== null) {
            $result['refusal'] = $this->refusal;
        }

        if ($this->toolCallId !== null) {
            $result['tool_call_id'] = $this->toolCallId;
        }

        if ($this->functionCall !== null) {
            $result['function_call'] = $this->functionCall;
        }

        if ($this->audio !== null) {
            $result['audio'] = $this->audio;
        }

        if ($this->toolCalls !== null) {
            $result['tool_calls'] = $this->toolCalls;
        }

        return $result;
    }
}
