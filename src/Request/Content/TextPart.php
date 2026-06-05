<?php

namespace Knivey\OpenAi\Request\Content;

readonly class TextPart implements ContentPart
{
    public function __construct(public string $text)
    {
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return ['type' => 'text', 'text' => $this->text];
    }
}
