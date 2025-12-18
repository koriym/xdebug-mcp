<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp;

use function in_array;
use function preg_match;
use function str_starts_with;

/**
 * Helper class for container command detection and PHP command location
 *
 * Provides shared utilities for Docker, Podman, and Kubectl container commands
 */
final class ContainerHelper
{
    private const CONTAINER_COMMANDS = ['docker', 'podman', 'kubectl'];

    /**
     * Check if the command parts represent a container command
     *
     * @param string[] $parts Command parts
     */
    public static function isContainerCommand(array $parts): bool
    {
        return isset($parts[0]) && in_array($parts[0], self::CONTAINER_COMMANDS, true);
    }

    /**
     * Find the position of PHP command within a command array
     * Returns the position of the LAST 'php' or 'phpX.Y' occurrence
     *
     * @param string[] $parts Command parts
     *
     * @return int|false Position of PHP command or false if not found
     */
    public static function findPhpCommandIndex(array $parts): int|false
    {
        $lastPhpIndex = false;

        foreach ($parts as $index => $part) {
            if ($part === 'php' || preg_match('/^php\d+\.\d+$/', $part)) {
                $lastPhpIndex = (int) $index;
            }
        }

        return $lastPhpIndex;
    }

    /**
     * Get the correct Xdebug client host based on container runtime
     *
     * @param string[] $command Command parts
     *
     * @return string The host alias for xdebug.client_host
     */
    public static function getContainerClientHost(array $command): string
    {
        $runtime = $command[0] ?? '';

        return match ($runtime) {
            'docker' => 'host.docker.internal',
            'podman' => 'host.containers.internal',
            'kubectl' => 'host.docker.internal', // Kubectl requires manual host configuration
            default => 'host.docker.internal',
        };
    }

    /**
     * Find the position to insert environment variable for Docker commands
     * Returns the index after 'run' or 'exec' subcommand
     *
     * @param string[] $parts Command parts
     *
     * @return int|false Position to insert -e flag or false if not applicable
     */
    public static function findDockerEnvInsertIndex(array $parts): int|false
    {
        foreach ($parts as $index => $part) {
            // For docker/podman/docker compose: insert after 'run' or 'exec'
            if ($part === 'run' || $part === 'exec') {
                return $index + 1;
            }
        }

        return false;
    }

    /** PHP CLI options that take a separate value argument */
    private const PHP_OPTIONS_WITH_VALUE = ['-d', '-c', '-z', '-B', '-R', '-F', '-E'];

    /**
     * Skip PHP options to find the actual script argument index
     *
     * @param string[] $parts    Command parts
     * @param int      $phpIndex Index of the 'php' command
     *
     * @return int|false Index of the script argument or false if not found
     */
    public static function findScriptIndex(array $parts, int $phpIndex): int|false
    {
        $scriptIndex = $phpIndex + 1;

        // Skip PHP options (starting with -)
        while (isset($parts[$scriptIndex]) && str_starts_with($parts[$scriptIndex], '-')) {
            $currentOption = $parts[$scriptIndex];
            $scriptIndex++;

            // If this option takes a value, skip the next argument too
            if (in_array($currentOption, self::PHP_OPTIONS_WITH_VALUE, true) && isset($parts[$scriptIndex])) {
                $scriptIndex++;
            }
        }

        return isset($parts[$scriptIndex]) ? $scriptIndex : false;
    }
}
