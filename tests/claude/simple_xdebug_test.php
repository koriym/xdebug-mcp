#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Basic Xdebug Connection Test
 *
 * This script tests if Xdebug can connect to a simple socket listener
 * without any MCP complexity.
 */

echo "🔍 Basic Xdebug Connection Test\n";
echo "================================\n\n";

$host = '127.0.0.1';
$port = 9004;

// Create a simple socket server
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if ($socket === false) {
    die("Failed to create socket\n");
}

socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 10, 'usec' => 0]);

if (! socket_bind($socket, $host, $port)) {
    die("Failed to bind to {$host}:{$port}\n");
}

if (! socket_listen($socket, 1)) {
    die("Failed to listen on socket\n");
}

echo "✅ Socket server listening on {$host}:{$port}\n";
echo "⏳ Waiting for Xdebug connection...\n";
echo "💡 Now run in another terminal:\n";
echo "   php -dxdebug.mode=debug -dxdebug.start_with_request=yes -dxdebug.client_host=127.0.0.1 -dxdebug.client_port=9004 -r 'echo \"Hello Xdebug\\n\";'\n\n";

$clientSocket = socket_accept($socket);
if ($clientSocket === false) {
    echo "❌ No connection received (timeout after 10 seconds)\n";
    echo "💡 This means Xdebug is not connecting to our listener\n";
    socket_close($socket);
    exit(1);
}

echo "🎉 SUCCESS! Xdebug connected!\n";
echo "📡 Reading initial data from Xdebug...\n";

// Read the initial XML response from Xdebug
$data = socket_read($clientSocket, 4096);
if ($data === false) {
    echo "❌ Failed to read data from Xdebug\n";
} else {
    echo "✅ Received data from Xdebug:\n";
    echo "---\n";
    echo $data;
    echo "---\n";
}

socket_close($clientSocket);
socket_close($socket);

echo "\n🏁 Test completed successfully!\n";
echo "💡 Now we know Xdebug can connect to our socket listener.\n";
