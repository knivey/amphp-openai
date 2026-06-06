<?php

namespace Knivey\OpenAi\Response;

readonly class Choice
{
    use ResponseHelpers;

    private function __construct(
        public int $index,
        public MessageResponse $message,
        public ?string $finishReason = null,
        public ?Logprobs $logprobs = null,
    ) {
    }

    /**
     * @param array<mixed, mixed> $data
     */
    public static function fromApiResponse(array $data): self
    {
        $messageRaw = $data['message'] ?? null;
        $message = is_array($messageRaw)
            ? MessageResponse::fromApiResponse($messageRaw)
            : MessageResponse::fromApiResponse([]);

        $logprobsRaw = $data['logprobs'] ?? null;

        return new self(
            index: self::getInt($data, 'index'),
            message: $message,
            finishReason: self::getOptionalString($data, 'finish_reason'),
            logprobs: Logprobs::fromApiResponse(is_array($logprobsRaw) ? $logprobsRaw : null),
        );
    }
}
