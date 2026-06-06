<?php

namespace Knivey\OpenAi\Response;

readonly class TopLogprob
{
    use ResponseHelpers;

    /**
     * @param array<mixed, mixed>|null $bytes
     */
    private function __construct(
        public string $token,
        public float $logprob,
        public ?array $bytes = null,
    ) {
    }

    /**
     * @param array<mixed, mixed> $data
     */
    public static function fromApiResponse(array $data): self
    {
        return new self(
            token: self::getString($data, 'token'),
            logprob: self::getFloat($data, 'logprob'),
            bytes: self::getOptionalArray($data, 'bytes'),
        );
    }
}
