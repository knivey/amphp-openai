<?php

namespace Knivey\OpenAi\Response;

readonly class ChatResponse
{
    use ResponseHelpers;

    /**
     * @param array<int, Choice> $choices
     */
    private function __construct(
        public string $id,
        public string $object,
        public int $created,
        public string $model,
        public array $choices,
        public ?Usage $usage = null,
        public ?string $serviceTier = null,
        public ?string $systemFingerprint = null,
        public ?ModerationResult $moderation = null,
    ) {
    }

    /**
     * @param array<mixed, mixed> $data
     */
    public static function fromApiResponse(array $data): self
    {
        $choicesRaw = $data['choices'] ?? null;
        $choices = is_array($choicesRaw)
            ? array_map(
                static fn (mixed $c): Choice => Choice::fromApiResponse(
                    is_array($c) ? $c : [],
                ),
                $choicesRaw,
            )
            : [];

        $usageRaw = $data['usage'] ?? null;
        $moderationRaw = $data['moderation'] ?? null;

        return new self(
            id: self::getString($data, 'id'),
            object: self::getString($data, 'object'),
            created: self::getInt($data, 'created'),
            model: self::getString($data, 'model'),
            choices: $choices,
            usage: is_array($usageRaw) ? Usage::fromApiResponse($usageRaw) : null,
            serviceTier: self::getOptionalString($data, 'service_tier'),
            systemFingerprint: self::getOptionalString($data, 'system_fingerprint'),
            moderation: is_array($moderationRaw) ? ModerationResult::fromApiResponse($moderationRaw) : null,
        );
    }
}
