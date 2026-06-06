<?php

namespace Knivey\OpenAi\Response;

readonly class TokenLogprob
{
    use ResponseHelpers;

    /**
     * @param array<mixed, mixed>|null $bytes
     * @param array<int, TopLogprob> $topLogprobs
     */
    private function __construct(
        public string $token,
        public float $logprob,
        public ?array $bytes = null,
        public array $topLogprobs = [],
    ) {
    }

    /**
     * @param array<mixed, mixed> $data
     */
    public static function fromApiResponse(array $data): self
    {
        $topLogprobsRaw = $data['top_logprobs'] ?? null;
        $topLogprobs = is_array($topLogprobsRaw)
            ? array_map(
                static fn (mixed $tl): TopLogprob => TopLogprob::fromApiResponse(
                    is_array($tl) ? $tl : [],
                ),
                $topLogprobsRaw,
            )
            : [];

        $bytesRaw = $data['bytes'] ?? null;

        return new self(
            token: self::getString($data, 'token'),
            logprob: self::getFloat($data, 'logprob'),
            bytes: is_array($bytesRaw) ? $bytesRaw : null,
            topLogprobs: $topLogprobs,
        );
    }
}
