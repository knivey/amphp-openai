<?php

namespace Knivey\OpenAi\Request;

readonly class Reasoning
{
    public function __construct(
        public ?string $effort = null,
        public ?int $maxTokens = null,
        public ?bool $exclude = null,
        public ?bool $enabled = null,
    ) {
    }

    public static function effort(string $effort): self
    {
        return new self(effort: $effort);
    }

    public static function maxTokens(int $maxTokens): self
    {
        return new self(maxTokens: $maxTokens);
    }

    public static function enabled(): self
    {
        return new self(enabled: true);
    }

    /**
     * @return array<string, bool|int|string>
     */
    public function toArray(): array
    {
        $result = [];
        if ($this->effort !== null) {
            $result['effort'] = $this->effort;
        }
        if ($this->maxTokens !== null) {
            $result['max_tokens'] = $this->maxTokens;
        }
        if ($this->exclude !== null) {
            $result['exclude'] = $this->exclude;
        }
        if ($this->enabled !== null) {
            $result['enabled'] = $this->enabled;
        }
        return $result;
    }
}
