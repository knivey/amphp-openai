<?php

namespace Knivey\OpenAi\Request\Audio;

readonly class AudioOutputOptions
{
    public function __construct(
        public string $voice,
        public string $format = 'wav',
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return ['voice' => $this->voice, 'format' => $this->format];
    }
}
