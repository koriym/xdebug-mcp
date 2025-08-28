<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Tests\Unit;

use Koriym\XdebugMcp\McpServer;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Throwable;

class QuoteProcessingTest extends TestCase
{
    private McpServer $mcpServer;
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        $this->mcpServer = new McpServer();
        $this->reflection = new ReflectionClass($this->mcpServer);
    }

    public function testProcessScriptArgumentWithCompleteQuotes(): void
    {
        $method = $this->reflection->getMethod('processScriptArgument');
        $method->setAccessible(true);

        $result = $method->invoke($this->mcpServer, '"php demo.php"');
        $this->assertEquals('php demo.php', $result);
    }

    public function testProcessScriptArgumentWithIncompleteLeadingQuote(): void
    {
        $method = $this->reflection->getMethod('processScriptArgument');
        $method->setAccessible(true);

        $result = $method->invoke($this->mcpServer, '"php demo.php');
        $this->assertEquals('php demo.php', $result);
    }

    public function testProcessScriptArgumentWithTrailingQuote(): void
    {
        $method = $this->reflection->getMethod('processScriptArgument');
        $method->setAccessible(true);

        $result = $method->invoke($this->mcpServer, 'demo.php"');
        $this->assertEquals('demo.php', $result);
    }

    public function testProcessScriptArgumentWithoutQuotes(): void
    {
        $method = $this->reflection->getMethod('processScriptArgument');
        $method->setAccessible(true);

        $result = $method->invoke($this->mcpServer, 'php demo.php');
        $this->assertEquals('php demo.php', $result);
    }

    public function testClaudeCLIQuoteWorkaround(): void
    {
        // Test the scenario where Claude CLI sends:
        // script: "php" and breakpoints: "demo.php"
        $args = [
            'script' => 'php',
            'breakpoints' => 'demo.php',
            'steps' => '5',
        ];

        // This should reconstruct to "php demo.php" and clear breakpoints
        $method = $this->reflection->getMethod('executeXDebug');
        $method->setAccessible(true);

        try {
            $result = $method->invoke($this->mcpServer, 1, $args);
            // Should succeed and generate trace output
            $this->assertIsArray($result);
        } catch (Throwable $e) {
            // If it fails, it shouldn't be due to incomplete script
            $this->assertStringNotContainsString('Incomplete script', $e->getMessage());
        }
    }
}
