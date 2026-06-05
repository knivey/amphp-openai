<?php

namespace Knivey\OpenAi\Request\Content;

readonly class AudioPart implements ContentPart
{
    public function __construct(
        public string $data,
        public string $format,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => 'input_audio',
            'input_audio' => ['data' => $this->data, 'format' => $this->format],
        ];
    }
}
