<?php

namespace Knivey\OpenAi\Request\Tool;

readonly class FunctionTool implements ToolDefinition
{
    public function __construct(
        public string $name,
        private ?string $description = null,
        /** @var array<string, mixed>|null */
        private ?array $parameters = null,
        private ?bool $strict = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $function = ['name' => $this->name];

        if ($this->description !== null) {
            $function['description'] = $this->description;
        }

        if ($this->parameters !== null) {
            $function['parameters'] = $this->parameters;
        }

        if ($this->strict !== null) {
            $function['strict'] = $this->strict;
        }

        return ['type' => 'function', 'function' => $function];
    }
}
