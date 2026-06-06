<?php

namespace Knivey\OpenAi\Response;

readonly class MessageResponse
{
    use ResponseHelpers;

    /**
     * @param array<int, Annotation>|null $annotations
     * @param array<mixed, mixed>|null $audio
     * @param array<int, ToolCall>|null $toolCalls
     * @param array<mixed, mixed>|null $functionCall
     */
    private function __construct(
        public string $role,
        public ?string $content = null,
        public ?string $refusal = null,
        public ?array $annotations = null,
        public ?array $audio = null,
        public ?array $toolCalls = null,
        public ?array $functionCall = null,
    ) {
    }

    /**
     * @param array<mixed, mixed> $data
     */
    public static function fromApiResponse(array $data): self
    {
        $annotationsRaw = $data['annotations'] ?? null;
        $annotations = is_array($annotationsRaw)
            ? array_map(
                static fn (mixed $a): Annotation => Annotation::fromApiResponse(
                    is_array($a) ? $a : [],
                ),
                $annotationsRaw,
            )
            : null;

        $toolCallsRaw = $data['tool_calls'] ?? null;
        $toolCalls = is_array($toolCallsRaw)
            ? array_map(
                static fn (mixed $tc): ToolCall => ToolCall::fromApiResponse(
                    is_array($tc) ? $tc : [],
                ),
                $toolCallsRaw,
            )
            : null;

        return new self(
            role: self::getString($data, 'role'),
            content: self::getOptionalString($data, 'content'),
            refusal: self::getOptionalString($data, 'refusal'),
            annotations: $annotations,
            audio: self::getOptionalArray($data, 'audio'),
            toolCalls: $toolCalls,
            functionCall: self::getOptionalArray($data, 'function_call'),
        );
    }
}
