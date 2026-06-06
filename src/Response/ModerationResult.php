<?php

namespace Knivey\OpenAi\Response;

readonly class ModerationResult
{
    use ResponseHelpers;

    /**
     * @param array<mixed, mixed>|null $input
     * @param array<mixed, mixed>|null $output
     */
    private function __construct(
        public ?array $input = null,
        public ?array $output = null,
    ) {
    }

    /**
     * @param array<mixed, mixed> $data
     */
    public static function fromApiResponse(array $data): self
    {
        return new self(
            input: self::getOptionalArray($data, 'input'),
            output: self::getOptionalArray($data, 'output'),
        );
    }
}
