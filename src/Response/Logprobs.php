<?php

namespace Knivey\OpenAi\Response;

readonly class Logprobs
{
    /**
     * @param array<int, TokenLogprob>|null $content
     * @param array<int, TokenLogprob>|null $refusal
     */
    private function __construct(
        public ?array $content = null,
        public ?array $refusal = null,
    ) {
    }

    /**
     * @param array<mixed, mixed>|null $data
     */
    public static function fromApiResponse(?array $data): ?self
    {
        if ($data === null) {
            return null;
        }

        $contentRaw = $data['content'] ?? null;
        $content = is_array($contentRaw)
            ? array_map(
                static fn (mixed $t): TokenLogprob => TokenLogprob::fromApiResponse(
                    is_array($t) ? $t : [],
                ),
                $contentRaw,
            )
            : null;

        $refusalRaw = $data['refusal'] ?? null;
        $refusal = is_array($refusalRaw)
            ? array_map(
                static fn (mixed $t): TokenLogprob => TokenLogprob::fromApiResponse(
                    is_array($t) ? $t : [],
                ),
                $refusalRaw,
            )
            : null;

        return new self(
            content: $content,
            refusal: $refusal,
        );
    }
}
