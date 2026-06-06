<?php

namespace Knivey\OpenAi\Request\Tool;

use Knivey\OpenAi\Response\ToolCall;

class ToolRegistry
{
    /** @var array<string, ReflectionTool> */
    private array $tools = [];

    public static function create(): self
    {
        return new self();
    }

    public function add(ReflectionTool $tool): self
    {
        $this->tools[$tool->name] = $tool;
        return $this;
    }

    public function get(string $name): ReflectionTool
    {
        if (!isset($this->tools[$name])) {
            throw new \InvalidArgumentException("Tool '{$name}' not found in registry.");
        }
        return $this->tools[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    /**
     * @return list<ReflectionTool>
     */
    public function getTools(): array
    {
        return array_values($this->tools);
    }

    public function dispatch(string $name, string $argumentsJson): mixed
    {
        return $this->get($name)->invoke($argumentsJson);
    }

    /**
     * @param list<ToolCall> $toolCalls
     * @return array<string, mixed>
     */
    public function dispatchAll(array $toolCalls): array
    {
        $results = [];
        foreach ($toolCalls as $toolCall) {
            $function = $toolCall->function;
            if ($function === null) {
                continue;
            }
            $name = $function['name'] ?? null;
            $arguments = $function['arguments'] ?? null;
            if (!\is_string($name) || !\is_string($arguments)) {
                continue;
            }
            $results[$toolCall->id] = $this->dispatch($name, $arguments);
        }
        return $results;
    }
}
