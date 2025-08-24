<?php

function simulateN1Problem() {
    echo "Simulating N+1 problem...\n";
    
    // これらの関数名はXdebugTracerのdbFunctionsに含まれている
    // 実際のDB呼び出しではないが、関数名でカウントされる
    
    // Initial query (1回目)
    mysqli_query("connection", "SELECT * FROM users");
    
    // N+1: Each user query (5回)
    for ($i = 1; $i <= 5; $i++) {
        PDO_prepare("SELECT * FROM posts WHERE user_id = ?");
        PDO_execute([$i]);
    }
    
    // Additional heavy query (1回)
    pg_query("SELECT * FROM analytics");
    
    return 5;
}

// These functions exist in XdebugTracer::$dbFunctions
function mysqli_query($conn, $query) {
    echo "DB Query: $query\n";
    return true;
}

function PDO_prepare($query) {
    echo "PDO Prepare: $query\n"; 
    return true;
}

function PDO_execute($params) {
    echo "PDO Execute with params: " . json_encode($params) . "\n";
    return true;
}

function pg_query($query) {
    echo "PostgreSQL Query: $query\n";
    return true;
}

echo "Starting real DB function test...\n";
$result = simulateN1Problem();
echo "Completed with $result users processed.\n";