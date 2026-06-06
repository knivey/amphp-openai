<?php

namespace Knivey\OpenAi\Response;

readonly class ChunkChoice
{
    use ResponseHelpers;

    /**
     * @param array<mixed, mixed> $delta
     */
    private function __construct(
        public int $index,
        public array $delta,
        public ?string $finishReason = null,
        public ?Logprobs $logprobs = null,
    ) {
    }

    /**
     * @param array<mixed, mixed> $data
     */
    public static function fromApiResponse(array $data): self
    {
        $deltaRaw = $data['delta'] ?? null;
        $delta = is_array($deltaRaw) ? $deltaRaw : [];

        $logprobsRaw = $data['logprobs'] ?? null;

        return new self(
            index: self::getInt($data, 'index'),
            delta: $delta,
            finishReason: self::getOptionalString($data, 'finish_reason'),
            logprobs: Logprobs::fromApiResponse(is_array($logprobsRaw) ? $logprobsRaw : null),
        );
    }
}
