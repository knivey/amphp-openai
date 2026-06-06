<?php

namespace Knivey\OpenAi\Response;

readonly class ToolCall
{
    use ResponseHelpers;

    /**
     * @param array<mixed, mixed>|null $function
     * @param array<mixed, mixed>|null $custom
     */
    private function __construct(
        public string $id,
        public string $type,
        public ?array $function = null,
        public ?array $custom = null,
    ) {
    }

    /**
     * @param array<mixed, mixed> $data
     */
    public static function fromApiResponse(array $data): self
    {
        return new self(
            id: self::getString($data, 'id'),
            type: self::getString($data, 'type'),
            function: self::getOptionalArray($data, 'function'),
            custom: self::getOptionalArray($data, 'custom'),
        );
    }
}
