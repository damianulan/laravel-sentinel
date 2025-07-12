<?php

namespace Sentinel\Config;

abstract readonly class Warden
{

    /**
     * Returns a list of all enum values.
     *
     * @return array<int, string|int>
     */
    public static function values(): array
    {
        return array_values(static::cases());
    }

    /**
     * Returns a map of enum values to human-readable labels.
     * Should be overridden by child classes.
     *
     * @return array<string|int, string>
     */
    public static function labels(): array
    {
        return [];
    }

    /**
     * Returns an associative array of constant names to values.
     * Uses reflection and caches the result.
     *
     * @return array<string, string|int>
     */
    public static function cases(): array
    {
        $class = static::class;
        $reflection = new \ReflectionClass($class);

        return $reflection->getConstants();
    }
}
