<?php

namespace Knivey\OpenAi\Response;

readonly class StreamingOptions
{
    public function __construct(
        private ?bool $includeUsage = null,
        private ?bool $includeObfuscation = null,
    ) {
    }

    /**
     * @return array<string, bool>
     */
    public function toArray(): array
    {
        $result = [];

        if ($this->includeUsage !== null) {
            $result['include_usage'] = $this->includeUsage;
        }

        if ($this->includeObfuscation !== null) {
            $result['include_obfuscation'] = $this->includeObfuscation;
        }

        return $result;
    }
}
