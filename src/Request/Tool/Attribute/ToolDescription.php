<?php

namespace Knivey\OpenAi\Request\Tool\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION)]
readonly class ToolDescription
{
    public function __construct(public string $description) {}
}
