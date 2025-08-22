<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use XdebugMcp\Tests\Fake\FakeMcpServer;

echo "=== Xdebug MCP Server Demo ===\n\n";

$server = new FakeMcpServer();

// Demo test cases
$demoSteps = [
    [
        'name' => 'Initialization',
        'request' => [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => []
        ]
    ],
    [
        'name' => 'Connect to Xdebug',
        'request' => [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/call',
            'params' => [
                'name' => 'xdebug_connect',
                'arguments' => ['host' => '127.0.0.1', 'port' => 9003]
            ]
        ]
    ],
    [
        'name' => 'Set breakpoint',
        'request' => [
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => [
                'name' => 'xdebug_set_breakpoint',
                'arguments' => [
                    'filename' => __DIR__ . '/sample.php',
                    'line' => 5
                ]
            ]
        ]
    ],
    [
        'name' => 'Step over execution',
        'request' => [
            'jsonrpc' => '2.0',
            'id' => 4,
            'method' => 'tools/call',
            'params' => [
                'name' => 'xdebug_step_over',
                'arguments' => []
            ]
        ]
    ],
    [
        'name' => 'Get stack trace',
        'request' => [
            'jsonrpc' => '2.0',
            'id' => 5,
            'method' => 'tools/call',
            'params' => [
                'name' => 'xdebug_get_stack',
                'arguments' => []
            ]
        ]
    ],
    [
        'name' => 'Get local variables',
        'request' => [
            'jsonrpc' => '2.0',
            'id' => 6,
            'method' => 'tools/call',
            'params' => [
                'name' => 'xdebug_get_variables',
                'arguments' => ['context' => 0]
            ]
        ]
    ],
    [
        'name' => 'Evaluate PHP expression',
        'request' => [
            'jsonrpc' => '2.0',
            'id' => 7,
            'method' => 'tools/call',
            'params' => [
                'name' => 'xdebug_eval',
                'arguments' => ['expression' => '$sum']
            ]
        ]
    ],
    [
        'name' => 'Step into execution',
        'request' => [
            'jsonrpc' => '2.0',
            'id' => 8,
            'method' => 'tools/call',
            'params' => [
                'name' => 'xdebug_step_into',
                'arguments' => []
            ]
        ]
    ],
    [
        'name' => 'Continue execution',
        'request' => [
            'jsonrpc' => '2.0',
            'id' => 9,
            'method' => 'tools/call',
            'params' => [
                'name' => 'xdebug_continue',
                'arguments' => []
            ]
        ]
    ],
    [
        'name' => 'Evaluate array',
        'request' => [
            'jsonrpc' => '2.0',
            'id' => 10,
            'method' => 'tools/call',
            'params' => [
                'name' => 'xdebug_eval',
                'arguments' => ['expression' => 'count($numbers)']
            ]
        ]
    ]
];

// Use reflection to access private methods
$reflection = new ReflectionClass($server);
$handleRequestMethod = $reflection->getMethod('handleRequest');
$handleRequestMethod->setAccessible(true);

foreach ($demoSteps as $i => $step) {
    echo "--- Step " . ($i + 1) . ": {$step['name']} ---\n";
    
    try {
        $response = $handleRequestMethod->invoke($server, $step['request']);
        
        if (isset($response['result']['content'][0]['text'])) {
            echo $response['result']['content'][0]['text'] . "\n";
        } else {
            echo json_encode($response, JSON_PRETTY_PRINT) . "\n";
        }
        
        echo "✅ Success\n\n";
        
        // Make demo progress more visible
        usleep(500000); // Wait 0.5 seconds
        
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n\n";
    }
}

echo "=== Demo completed ===\n";