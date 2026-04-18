<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp;

use RuntimeException;

use function array_map;
use function escapeshellarg;
use function filemtime;
use function glob;
use function implode;
use function passthru;
use function usort;

final class XdebugCommandExecutor
{
    /** @param list<string> $commandParts */
    public static function buildPhpCommand(array $commandParts): string
    {
        return 'php ' . implode(' ', array_map(escapeshellarg(...), $commandParts));
    }

    public static function executeAndAssertSuccess(string $command): void
    {
        $exitCode = 0;
        passthru($command, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException("PHP execution failed with exit code: $exitCode");
        }
    }

    public static function findLatestArtifact(string $pattern, string $errorMessage): string
    {
        $files = glob($pattern);
        if ($files === [] || $files === false) {
            throw new RuntimeException($errorMessage);
        }

        usort($files, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        return $files[0];
    }
}
