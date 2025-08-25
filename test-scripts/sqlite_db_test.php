<?php

function createInMemoryDatabase() {
    echo "Creating in-memory SQLite database...\n";
    $pdo = new PDO('sqlite::memory:');
    
    // Create tables
    $pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)");
    $pdo->exec("CREATE TABLE posts (id INTEGER PRIMARY KEY, user_id INTEGER, title TEXT)");
    
    // Insert test data
    $pdo->exec("INSERT INTO users (name) VALUES ('Alice')");
    $pdo->exec("INSERT INTO users (name) VALUES ('Bob')"); 
    $pdo->exec("INSERT INTO users (name) VALUES ('Charlie')");
    
    $pdo->exec("INSERT INTO posts (user_id, title) VALUES (1, 'Post 1')");
    $pdo->exec("INSERT INTO posts (user_id, title) VALUES (1, 'Post 2')");
    $pdo->exec("INSERT INTO posts (user_id, title) VALUES (2, 'Post 3')");
    
    return $pdo;
}

function demonstrateN1Problem($pdo) {
    echo "Demonstrating N+1 query problem...\n";
    
    // Initial query to get all users (1 query)
    $stmt = $pdo->prepare("SELECT * FROM users");
    $stmt->execute();
    $users = $stmt->fetchAll();
    
    // N problem: For each user, get their posts (N queries)
    foreach ($users as $user) {
        $stmt = $pdo->prepare("SELECT * FROM posts WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $posts = $stmt->fetchAll();
        echo "User {$user['name']} has " . count($posts) . " posts\n";
    }
    
    // Additional query
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM posts");
    $total = $stmt->fetch();
    echo "Total posts: {$total['total']}\n";
    
    return count($users);
}

echo "Starting SQLite DB test...\n";

try {
    $pdo = createInMemoryDatabase();
    $result = demonstrateN1Problem($pdo);
    echo "Processed $result users with N+1 queries.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "SQLite DB test completed.\n";