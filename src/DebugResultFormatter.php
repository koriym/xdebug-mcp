<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp;

use Closure;

use function json_encode;
use function str_repeat;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * @phpstan-type BreakLocation array{file: string, line: int}
 * @phpstan-type WatchState array{expression: string, value: string, previous?: string|null, reason: string}
 * @phpstan-type BreakState array{
 *     step: int,
 *     location: BreakLocation,
 *     variables: array<string, string>,
 *     recording_type?: string,
 *     watches?: list<WatchState>
 * }
 * @phpstan-type TraceInfo array{file: string, lines: int, functions: int, max_depth: int, db_queries: int, error?: string}
 * @phpstan-type BreakpointPayload array{
 *     '$schema': string,
 *     breaks: list<BreakState>,
 *     trace: TraceInfo,
 *     context?: string
 * }
 */
final class DebugResultFormatter
{
    /**
     * @param list<BreakState> $breaks
     * @param TraceInfo        $traceInfo
     *
     * @return BreakpointPayload
     */
    public function buildBreakpointPayload(array $breaks, array $traceInfo, string $context = ''): array
    {
        $payload = [
            '$schema' => 'https://koriym.github.io/xdebug-mcp/schemas/xstep.json',
            'breaks' => $breaks,
            'trace' => $traceInfo,
        ];

        if ($context !== '') {
            $payload['context'] = $context;
        }

        return $payload;
    }

    /** @param BreakpointPayload $payload */
    public function emit(array $payload, bool $jsonOutput, Closure $logger): void
    {
        if ($jsonOutput) {
            echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

            return;
        }

        $logger("\n" . str_repeat('=', 60));
        $logger('🎯 MULTIPLE BREAKPOINTS DEBUG RESULT');
        $logger(str_repeat('=', 60));

        foreach ($payload['breaks'] as $break) {
            $loc = $break['location'];
            $logger("📍 Step {$break['step']}: {$loc['file']}:{$loc['line']}");

            if ($break['variables'] !== []) {
                $logger('📊 Variables:');
                foreach ($break['variables'] as $name => $value) {
                    $logger("  {$name} = {$value}");
                }
            }

            $logger('');
        }

        if ($payload['trace']['file'] === '') {
            return;
        }

        $logger("📈 Trace file: {$payload['trace']['file']}");
        $logger("📊 Trace lines: {$payload['trace']['lines']}");
    }
}
