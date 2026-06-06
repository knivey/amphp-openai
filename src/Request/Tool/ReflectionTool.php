<?php

namespace Knivey\OpenAi\Request\Tool;

use Knivey\OpenAi\Request\Tool\Attribute\ToolDescription;
use Knivey\OpenAi\Request\Tool\Attribute\ToolParam;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use BackedEnum;

class ReflectionTool implements ToolDefinition
{
    private readonly \ReflectionFunctionAbstract $reflection;
    private readonly \Closure $callable;
    /** @var array<string, mixed> */
    private readonly array $parametersSchema;
    private readonly ?string $description;
    private readonly ?bool $strict;

    private function __construct(
        \ReflectionFunctionAbstract $reflection,
        \Closure $callable,
        public readonly string $name,
        ?string $description = null,
        ?bool $strict = null,
    ) {
        $this->reflection = $reflection;
        $this->callable = $callable;
        $this->description = $description;
        $this->strict = $strict;
        $this->parametersSchema = $this->buildParametersSchema();
    }

    public static function fromCallable(
        callable $callable,
        ?string $name = null,
        ?string $description = null,
        ?bool $strict = null,
    ): self {
        $reflection = new ReflectionFunction(\Closure::fromCallable($callable));

        $resolvedName = $name;
        if ($resolvedName === null) {
            $resolvedName = $reflection->getName();
        }
        if (str_starts_with($resolvedName, '{closure') || $resolvedName === 'Closure') {
            throw new \InvalidArgumentException(
                'Closures require an explicit tool name via the $name parameter.',
            );
        }

        $resolvedDescription = $description;
        if ($resolvedDescription === null) {
            $resolvedDescription = self::readDescriptionAttribute($reflection);
        }

        return new self($reflection, \Closure::fromCallable($callable), $resolvedName, $resolvedDescription, $strict);
    }

    /**
     * @param array{class-string|object, string}|string $method
     */
    public static function fromMethod(
        array|string $method,
        ?string $name = null,
        ?string $description = null,
        ?bool $strict = null,
    ): self {
        if (is_string($method) && str_contains($method, '::')) {
            $reflection = new ReflectionMethod($method);
        } elseif (is_array($method)) {
            $reflection = new ReflectionMethod($method[0], $method[1]);
        } else {
            throw new \InvalidArgumentException(
                'Method must be [object, method] or "Class::method".',
            );
        }

        $resolvedName = $name ?? $reflection->getName();

        $resolvedDescription = $description;
        if ($resolvedDescription === null) {
            $resolvedDescription = self::readDescriptionAttribute($reflection);
        }

        if ($reflection->isStatic()) {
            $closure = $reflection->getClosure(null);
        } else {
            $object = is_array($method) ? $method[0] : null;
            $closure = $reflection->getClosure(is_object($object) ? $object : null);
        }

        return new self($reflection, $closure, $resolvedName, $resolvedDescription, $strict);
    }

    public function invoke(string $argumentsJson): mixed
    {
        $args = json_decode($argumentsJson, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($args)) {
            $args = [];
        }

        $namedArgs = [];
        foreach ($this->reflection->getParameters() as $param) {
            $paramName = $param->getName();
            if (array_key_exists($paramName, $args)) {
                $namedArgs[$paramName] = $args[$paramName];
            } elseif ($param->isDefaultValueAvailable()) {
                $namedArgs[$paramName] = $param->getDefaultValue();
            } else {
                throw new \InvalidArgumentException(
                    "Missing required parameter '{$paramName}' for tool '{$this->name}'.",
                );
            }
        }

        return ($this->callable)(...$namedArgs);
    }

    public function toArray(): array
    {
        $function = ['name' => $this->name];

        if ($this->description !== null) {
            $function['description'] = $this->description;
        }

        $function['parameters'] = $this->parametersSchema;

        if ($this->strict !== null) {
            $function['strict'] = $this->strict;
        }

        return ['type' => 'function', 'function' => $function];
    }

    private static function readDescriptionAttribute(\ReflectionFunctionAbstract $reflection): ?string
    {
        $attrs = $reflection->getAttributes(ToolDescription::class);
        if ($attrs !== []) {
            return $attrs[0]->newInstance()->description;
        }
        return null;
    }

    private static function readParamAttribute(ReflectionParameter $param): ?ToolParam
    {
        $attrs = $param->getAttributes(ToolParam::class);
        if ($attrs !== []) {
            return $attrs[0]->newInstance();
        }
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildParametersSchema(): array
    {
        $properties = [];
        $required = [];

        foreach ($this->reflection->getParameters() as $param) {
            $type = $param->getType();
            if ($type === null) {
                throw new \InvalidArgumentException(
                    "Parameter '\${$param->getName()}' on tool '{$this->name}' has no type hint. All parameters must be typed.",
                );
            }
            if (!($type instanceof ReflectionNamedType) || $type->getName() === 'mixed') {
                throw new \InvalidArgumentException(
                    "Parameter '\${$param->getName()}' on tool '{$this->name}' must have a concrete type hint.",
                );
            }

            $prop = $this->buildPropertySchema($type, $param);

            if ($param->isDefaultValueAvailable()) {
                $prop['default'] = $param->getDefaultValue();
            } else {
                $required[] = $param->getName();
            }

            $properties[$param->getName()] = $prop;
        }

        $schema = ['type' => 'object', 'properties' => $properties];
        if ($required !== []) {
            $schema['required'] = $required;
        }
        return $schema;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPropertySchema(ReflectionNamedType $type, ReflectionParameter $param): array
    {
        $typeName = $type->getName();
        $attr = self::readParamAttribute($param);
        $prop = [];

        if ($attr !== null && $attr->type !== null) {
            $prop['type'] = $attr->type;
        } elseif (is_a($typeName, BackedEnum::class, true)) {
            $backingType = (new \ReflectionEnum($typeName))->getBackingType();
            $prop['type'] = ($backingType !== null && $backingType->getName() === 'int') ? 'integer' : ($backingType?->getName() ?? 'string');
        } else {
            $prop['type'] = match ($typeName) {
                'string' => 'string',
                'int' => 'integer',
                'float' => 'number',
                'bool' => 'boolean',
                'array' => 'object',
                default => throw new \InvalidArgumentException(
                    "Unsupported type '{$typeName}' for parameter '\${$param->getName()}' on tool '{$this->name}'.",
                ),
            };
        }

        if (is_a($typeName, BackedEnum::class, true)) {
            if ($attr !== null && $attr->enum !== null) {
                $prop['enum'] = $attr->enum;
            } else {
                $cases = [];
                foreach (($typeName::cases()) as $case) {
                    $cases[] = $case->value;
                }
                $prop['enum'] = $cases;
            }
        } elseif ($attr !== null && $attr->enum !== null) {
            $prop['enum'] = $attr->enum;
        }

        if ($attr !== null && $attr->description !== null) {
            $prop['description'] = $attr->description;
        }

        return $prop;
    }
}
