<?php

namespace Knivey\OpenAi\Request\Tool;

readonly class CustomTool implements ToolDefinition
{
    public function __construct(
        public string $name,
        private ?string $description = null,
        /** @var array<string, mixed>|null */
        private ?array $format = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $custom = ['name' => $this->name];

        if ($this->description !== null) {
            $custom['description'] = $this->description;
        }

        if ($this->format !== null) {
            $custom['format'] = $this->format;
        }

        return ['type' => 'custom', 'custom' => $custom];
    }
}
