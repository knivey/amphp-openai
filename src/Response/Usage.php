<?php

namespace Knivey\OpenAi\Response;

readonly class Usage
{
    use ResponseHelpers;

    /**
     * @param array<mixed, mixed>|null $completionTokensDetails
     * @param array<mixed, mixed>|null $promptTokensDetails
     */
    private function __construct(
        public int $promptTokens,
        public int $completionTokens,
        public int $totalTokens,
        public ?array $completionTokensDetails = null,
        public ?array $promptTokensDetails = null,
    ) {
    }

    /**
     * @param array<mixed, mixed> $data
     */
    public static function fromApiResponse(array $data): self
    {
        return new self(
            promptTokens: self::getInt($data, 'prompt_tokens'),
            completionTokens: self::getInt($data, 'completion_tokens'),
            totalTokens: self::getInt($data, 'total_tokens'),
            completionTokensDetails: self::getOptionalArray($data, 'completion_tokens_details'),
            promptTokensDetails: self::getOptionalArray($data, 'prompt_tokens_details'),
        );
    }
}
