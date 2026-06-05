<?php

namespace Knivey\OpenAi\Request\Tool;

interface ToolDefinition
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
