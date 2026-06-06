<?php

namespace Knivey\OpenAi\Request\Audio;

readonly class AudioOutputOptions
{
    /**
     * @param string|array{id: string} $voice
     */
    public function __construct(
        public readonly string|array $voice,
        public readonly string $format = 'wav',
    ) {
    }

    /**
     * @return array<string, string|array{id: string}>
     */
    public function toArray(): array
    {
        return ['voice' => $this->voice, 'format' => $this->format];
    }
}
