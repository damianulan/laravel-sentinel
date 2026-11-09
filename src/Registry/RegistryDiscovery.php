<?php

declare(strict_types=1);

namespace Sentinel\Registry;

use ReflectionClass;
use RuntimeException;
use Throwable;

final class RegistryDiscovery
{
    /** @var array<string, array<int, class-string>> */
    private array $implementations = [];

    public function __construct(
        private readonly string $classMapPath,
        private readonly ?string $projectPath = null,
    ) {}

    /** @return array<int, class-string> */
    public function implementing(string $contract): array
    {
        if (array_key_exists($contract, $this->implementations)) {
            return $this->implementations[$contract];
        }

        if ( ! is_file($this->classMapPath)) {
            throw new RuntimeException("Composer classmap not found at [{$this->classMapPath}]. Run composer dump-autoload --optimize.");
        }

        $classMap = require $this->classMapPath;

        if ( ! is_array($classMap)) {
            throw new RuntimeException("Composer classmap at [{$this->classMapPath}] is invalid.");
        }

        $registries = [];

        foreach ($classMap as $class => $path) {
            if ( ! is_string($class) || ! is_string($path) || ! $this->belongsToProject($path)) {
                continue;
            }

            try {
                if ( ! is_subclass_of($class, $contract, true)) {
                    continue;
                }

                $reflection = new ReflectionClass($class);
            } catch (Throwable) {
                continue;
            }

            if ($reflection->isAbstract() || $reflection->isInterface() || $reflection->isTrait()) {
                continue;
            }

            $registries[] = $class;
        }

        sort($registries);

        return $this->implementations[$contract] = $registries;
    }

    private function belongsToProject(string $path): bool
    {
        if (null === $this->projectPath) {
            return true;
        }

        $projectPath = preg_replace('#/+$#', '', str_replace('\\', '/', $this->projectPath))
            ?? $this->projectPath;
        $classPath = str_replace('\\', '/', $path);

        return str_starts_with($classPath, "{$projectPath}/")
            && ! str_starts_with($classPath, "{$projectPath}/vendor/");
    }
}
