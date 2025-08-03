<?php

namespace Sentinel\Config\Warden;

class Warden
{
    /**
     * Define additional roles/permissions that are not declared as constants.
     */
    public static function items(): array
    {
        return [];
    }

    /**
     * Returns a list of all role/permission values.
     *
     * @return array<int, string|int>
     */
    final public static function values(): array
    {
        $cases = array_values(static::cases());
        $items = array_values(static::items());

        return array_unique(array_merge($cases, $items));
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
    final public static function cases(): array
    {
        $class = static::class;
        $reflection = new \ReflectionClass($class);

        return $reflection->getConstants();
    }
}
