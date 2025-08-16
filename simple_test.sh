ph#!/bin/bash

echo "MCPサーバーの基本動作テスト"
echo "================================"

echo ""
echo "1. 構文チェック"
php -l src/McpServer.php
php -l src/XdebugClient.php
php -l bin/xdebug-mcp

echo ""
echo "2. 基本機能テスト"
php test_mcp.php

echo ""
echo "3. ツール一覧の確認"
echo '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{}}
{"jsonrpc":"2.0","id":2,"method":"tools/list","params":{}}' | timeout 5 php bin/xdebug-mcp | head -20

echo ""
echo "テスト完了！"
