<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function dirname;
use function file_get_contents;
use function json_decode;

use const JSON_THROW_ON_ERROR;

final class XstepSchemaTest extends TestCase
{
    #[Test]
    public function documentsStepRecordingType(): void
    {
        $schemaJson = file_get_contents(dirname(__DIR__, 2) . '/docs/schemas/xstep.json');
        $this->assertIsString($schemaJson);

        /** @var array{
         *     definitions: array{
         *         breakpoint: array{
         *             properties: array<string, array<string, mixed>>,
         *             required: list<string>
         *         }
         *     }
         * } $schema
         */
        $schema = json_decode($schemaJson, true, 512, JSON_THROW_ON_ERROR);
        $breakpoint = $schema['definitions']['breakpoint'];
        $properties = $breakpoint['properties'];

        $this->assertArrayHasKey('location', $properties);
        $this->assertArrayHasKey('function', $properties);
        $this->assertArrayHasKey('stack', $properties);
        $this->assertArrayHasKey('breakpoint', $properties);
        $this->assertArrayHasKey('variables', $properties);
        $this->assertArrayHasKey('diff', $properties);
        $this->assertContains('location', $breakpoint['required']);
        $this->assertContains('variables', $breakpoint['required']);

        $this->assertArrayHasKey('recording_type', $properties);
        $this->assertSame('string', $properties['recording_type']['type']);
        $this->assertSame(['full', 'diff'], $properties['recording_type']['enum']);
        $this->assertNotContains('recording_type', $breakpoint['required']);

        $this->assertArrayHasKey('stack_frame', $schema['definitions']);
        $this->assertArrayHasKey('breakpoint_reference', $schema['definitions']);
        $this->assertArrayHasKey('variable_diff', $schema['definitions']);
    }
}
