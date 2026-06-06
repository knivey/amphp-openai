<?php

namespace Knivey\OpenAi\Request\Tool\Attribute;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
readonly class ToolParam
{
    public function __construct(
        public ?string $description = null,
        public ?string $type = null,
        /** @var list<string>|null */
        public ?array $enum = null,
    ) {}
}
