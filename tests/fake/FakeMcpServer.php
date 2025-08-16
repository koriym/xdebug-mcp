<?php

namespace XdebugMcp\Tests\Fake;

use XdebugMcp\McpServer;

class FakeMcpServer extends McpServer
{
    private ?FakeXdebugClient $fakeXdebugClient = null;

    protected function connectToXdebug(array $args): string
    {
        $host = $args['host'] ?? '127.0.0.1';
        $port = $args['port'] ?? 9003;

        $this->fakeXdebugClient = new FakeXdebugClient($host, $port);
        $this->xdebugClient = $this->fakeXdebugClient; // 親クラスのプロパティも設定
        $result = $this->fakeXdebugClient->connect();
        
        return "Connected to FAKE Xdebug at {$host}:{$port}. Session: " . json_encode($result);
    }

    protected function disconnectFromXdebug(): string
    {
        if (!$this->fakeXdebugClient) {
            throw new \Exception('Not connected to Xdebug');
        }

        $this->fakeXdebugClient->disconnect();
        $this->fakeXdebugClient = null;
        $this->xdebugClient = null;
        
        return 'Disconnected from FAKE Xdebug';
    }

    protected function setBreakpoint(array $args): string
    {
        if (!$this->fakeXdebugClient) {
            throw new \Exception('Not connected to Xdebug');
        }

        $filename = $args['filename'];
        $line = $args['line'];
        $condition = $args['condition'] ?? '';

        $breakpointId = $this->fakeXdebugClient->setBreakpoint($filename, $line, $condition);
        
        return "FAKE Breakpoint set at {$filename}:{$line} (ID: {$breakpointId})";
    }

    protected function removeBreakpoint(array $args): string
    {
        if (!$this->fakeXdebugClient) {
            throw new \Exception('Not connected to Xdebug');
        }

        $breakpointId = $args['breakpoint_id'];
        $this->fakeXdebugClient->removeBreakpoint($breakpointId);
        
        return "FAKE Breakpoint {$breakpointId} removed";
    }

    protected function stepInto(): string
    {
        if (!$this->fakeXdebugClient) {
            throw new \Exception('Not connected to Xdebug');
        }

        $result = $this->fakeXdebugClient->stepInto();
        return "FAKE Step into completed: " . json_encode($result);
    }

    protected function stepOver(): string
    {
        if (!$this->fakeXdebugClient) {
            throw new \Exception('Not connected to Xdebug');
        }

        $result = $this->fakeXdebugClient->stepOver();
        return "FAKE Step over completed: " . json_encode($result);
    }

    protected function stepOut(): string
    {
        if (!$this->fakeXdebugClient) {
            throw new \Exception('Not connected to Xdebug');
        }

        $result = $this->fakeXdebugClient->stepOut();
        return "FAKE Step out completed: " . json_encode($result);
    }

    protected function continue(): string
    {
        if (!$this->fakeXdebugClient) {
            throw new \Exception('Not connected to Xdebug');
        }

        $result = $this->fakeXdebugClient->continue();
        return "FAKE Continue completed: " . json_encode($result);
    }

    protected function getStack(): string
    {
        if (!$this->fakeXdebugClient) {
            throw new \Exception('Not connected to Xdebug');
        }

        $stack = $this->fakeXdebugClient->getStack();
        return "FAKE Stack trace:\n" . json_encode($stack, JSON_PRETTY_PRINT);
    }

    protected function getVariables(array $args): string
    {
        if (!$this->fakeXdebugClient) {
            throw new \Exception('Not connected to Xdebug');
        }

        $context = $args['context'] ?? 0;
        $variables = $this->fakeXdebugClient->getVariables($context);
        
        return "FAKE Variables (context {$context}):\n" . json_encode($variables, JSON_PRETTY_PRINT);
    }

    protected function evaluateExpression(array $args): string
    {
        if (!$this->fakeXdebugClient) {
            throw new \Exception('Not connected to Xdebug');
        }

        $expression = $args['expression'];
        $result = $this->fakeXdebugClient->eval($expression);
        
        return "FAKE Evaluation result for '{$expression}':\n" . json_encode($result, JSON_PRETTY_PRINT);
    }

    public function processRequest(array $request): array
    {
        return $this->handleRequest($request);
    }

    protected function startProfiling(array $args): string
    {
        $outputFile = $args['output_file'] ?? '';
        return "FAKE Profiling started" . ($outputFile ? " (output: {$outputFile})" : "") . ". Collecting performance data...";
    }

    protected function stopProfiling(): string
    {
        return "FAKE Profiling stopped. Analysis complete: 0.25s total execution time, 150 functions called";
    }

    protected function getProfileInfo(): string
    {
        $info = [
            'profiler_status' => 'FAKE active',
            'output_dir' => '/tmp',
            'output_name' => 'fake_profile.out',
            'functions_tracked' => 150,
            'total_time' => '0.25s'
        ];
        return "FAKE Profile info:\n" . json_encode($info, JSON_PRETTY_PRINT);
    }

    protected function analyzeProfile(array $args): string
    {
        $profileFile = $args['profile_file'];
        $topFunctions = $args['top_functions'] ?? 10;
        
        $analysis = [
            'total_time' => 250000, // microseconds
            'total_functions' => 150,
            'top_functions' => [
                'calculateTax()' => ['calls' => 50, 'time' => 150000],
                'formatDate()' => ['calls' => 1250, 'time' => 50000],
                'validateInput()' => ['calls' => 200, 'time' => 25000],
                'logActivity()' => ['calls' => 75, 'time' => 15000],
                'connectDatabase()' => ['calls' => 10, 'time' => 10000]
            ],
            'file' => $profileFile,
            'size' => 8192
        ];
        
        return "FAKE Profile analysis for {$profileFile}:\n" . json_encode($analysis, JSON_PRETTY_PRINT);
    }

    protected function startCoverage(array $args): string
    {
        $includePatterns = $args['include_patterns'] ?? [];
        $excludePatterns = $args['exclude_patterns'] ?? [];
        $trackUnused = $args['track_unused'] ?? true;

        return "FAKE Code coverage started" . 
               ($includePatterns ? " (includes: " . implode(', ', $includePatterns) . ")" : "") .
               ($excludePatterns ? " (excludes: " . implode(', ', $excludePatterns) . ")" : "") .
               ". Tracking execution...";
    }

    protected function stopCoverage(): string
    {
        return "FAKE Code coverage stopped. Data collected from 15 files, 1,456 lines tracked";
    }

    protected function getCoverage(array $args): string
    {
        $format = $args['format'] ?? 'raw';
        
        $coverage = [
            '/app/src/User.php' => [1 => 1, 2 => 1, 3 => 0, 4 => 1, 5 => -1],
            '/app/src/Product.php' => [1 => 1, 2 => 1, 3 => 1, 4 => 0, 5 => 0],
            '/app/src/Utils.php' => [1 => 1, 2 => 0, 3 => 0, 4 => 0, 5 => 1]
        ];

        if ($format === 'summary') {
            $summary = $this->fakeCoverageSummary($coverage);
            return "FAKE Coverage summary:\n" . json_encode($summary, JSON_PRETTY_PRINT);
        }
        
        return "FAKE Code coverage data:\n" . json_encode($coverage, JSON_PRETTY_PRINT);
    }

    protected function analyzeCoverage(array $args): string
    {
        $coverageData = $args['coverage_data'] ?? [];
        $format = $args['format'] ?? 'text';
        $outputFile = $args['output_file'] ?? '';

        if (empty($coverageData)) {
            return "FAKE Error: No coverage data provided";
        }

        $analysis = [
            [
                'file' => '/app/src/User.php',
                'total_lines' => 5,
                'covered_lines' => 3,
                'uncovered_lines' => [3],
                'coverage_percentage' => 60.0
            ],
            [
                'file' => '/app/src/Product.php', 
                'total_lines' => 5,
                'covered_lines' => 3,
                'uncovered_lines' => [4, 5],
                'coverage_percentage' => 60.0
            ]
        ];

        $report = "FAKE Coverage Report (format: {$format})\n";
        $report .= "================================\n";
        foreach ($analysis as $fileInfo) {
            $report .= "File: {$fileInfo['file']}\n";
            $report .= "Coverage: {$fileInfo['coverage_percentage']}%\n";
            $report .= "Lines: {$fileInfo['covered_lines']}/{$fileInfo['total_lines']}\n\n";
        }

        if ($outputFile) {
            $report .= "Report would be saved to: {$outputFile}\n";
        }

        return $report;
    }

    protected function getCoverageSummary(array $args): string
    {
        $coverageData = $args['coverage_data'] ?? [
            '/app/src/User.php' => [1 => 1, 2 => 1, 3 => 0, 4 => 1, 5 => -1],
            '/app/src/Product.php' => [1 => 1, 2 => 1, 3 => 1, 4 => 0, 5 => 0],
            '/app/src/Utils.php' => [1 => 1, 2 => 0, 3 => 0, 4 => 0, 5 => 1]
        ];

        $summary = $this->fakeCoverageSummary($coverageData);
        return "FAKE Coverage Summary:\n" . json_encode($summary, JSON_PRETTY_PRINT);
    }

    private function fakeCoverageSummary(array $coverageData): array
    {
        $totalLines = 0;
        $coveredLines = 0;
        $fileCount = 0;

        foreach ($coverageData as $file => $lines) {
            $fileCount++;
            foreach ($lines as $lineNumber => $executed) {
                $totalLines++;
                if ($executed > 0) {
                    $coveredLines++;
                }
            }
        }

        $percentage = $totalLines > 0 ? round(($coveredLines / $totalLines) * 100, 2) : 0;

        return [
            'total_files' => $fileCount,
            'total_lines' => $totalLines,
            'covered_lines' => $coveredLines,
            'uncovered_lines' => $totalLines - $coveredLines,
            'coverage_percentage' => $percentage
        ];
    }
}