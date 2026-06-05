<?php

namespace Knivey\OpenAi\Request\Content;

interface ContentPart
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
