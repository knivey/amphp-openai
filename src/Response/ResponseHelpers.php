<?php

namespace Knivey\OpenAi\Response;

trait ResponseHelpers
{
    /**
     * @param array<mixed, mixed> $data
     */
    private static function getString(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        return is_string($value) ? $value : '';
    }

    /**
     * @param array<mixed, mixed> $data
     */
    private static function getInt(array $data, string $key): int
    {
        $value = $data[$key] ?? null;
        return is_int($value) ? $value : 0;
    }

    /**
     * @param array<mixed, mixed> $data
     */
    private static function getOptionalString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;
        return is_string($value) ? $value : null;
    }

    /**
     * @param array<mixed, mixed> $data
     * @return array<mixed, mixed>|null
     */
    private static function getOptionalArray(array $data, string $key): ?array
    {
        $value = $data[$key] ?? null;
        return is_array($value) ? $value : null;
    }

    /**
     * @param array<mixed, mixed> $data
     */
    private static function getFloat(array $data, string $key): float
    {
        $value = $data[$key] ?? null;
        return is_float($value) || is_int($value) ? (float) $value : 0.0;
    }
}
