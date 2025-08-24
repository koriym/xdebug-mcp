<?php

function simulateDatabaseQueries() {
    echo "Simulating database operations...\n";
    
    // Simulate N+1 problem
    $users = range(1, 5); // 5 users
    
    // Initial query to get all users (1 query)
    echo "SELECT * FROM users\n";
    
    // N+1: For each user, get their posts (5 more queries)
    foreach ($users as $userId) {
        echo "SELECT * FROM posts WHERE user_id = $userId\n";
    }
    
    // Additional heavy query
    echo "SELECT * FROM analytics WHERE date > '2024-01-01'\n";
    
    return count($users);
}

function databaseConnection() {
    // Simulate PDO connection
    echo "Connecting to database...\n";
    return true;
}

echo "Starting database test...\n";
$connected = databaseConnection();

if ($connected) {
    $result = simulateDatabaseQueries();
    echo "Processed $result users\n";
}

echo "Database test completed.\n";

// Note: This is a simulation - no actual PDO calls
// In real usage, this would contain actual:
// - PDO::prepare()
// - PDO::query() 
// - PDO::execute()
// - mysqli_query()
// etc.