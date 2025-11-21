#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Symfony-like application simulation for Docker integration testing
 * Simulates a REST API request handling workflow
 */

echo "=== Symfony-like API Simulation ===\n";

// Simulate Symfony components
class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $parameters = []
    ) {}
}

class Response
{
    public function __construct(
        public readonly mixed $content,
        public readonly int $statusCode = 200
    ) {}

    public function send(): void
    {
        http_response_code($this->statusCode);
        echo json_encode($this->content, JSON_PRETTY_PRINT) . "\n";
    }
}

class Router
{
    private array $routes = [];

    public function addRoute(string $path, string $method, callable $handler): void
    {
        $this->routes[] = [
            'path' => $path,
            'method' => $method,
            'handler' => $handler,
        ];
    }

    public function match(Request $request): ?callable
    {
        foreach ($this->routes as $route) {
            if ($route['path'] === $request->path && $route['method'] === $request->method) {
                return $route['handler'];
            }
        }
        return null;
    }
}

class UserService
{
    private array $users = [
        ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'],
        ['id' => 2, 'name' => 'Bob', 'email' => 'bob@example.com'],
        ['id' => 3, 'name' => 'Charlie', 'email' => 'charlie@example.com'],
    ];

    public function findAll(): array
    {
        // Simulate database query delay
        usleep(10000); // 10ms
        return $this->users;
    }

    public function findById(int $id): ?array
    {
        usleep(5000); // 5ms
        foreach ($this->users as $user) {
            if ($user['id'] === $id) {
                return $user;
            }
        }
        return null;
    }

    public function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

class UserController
{
    public function __construct(
        private readonly UserService $userService
    ) {}

    public function listUsers(Request $request): Response
    {
        $users = $this->userService->findAll();
        return new Response([
            'success' => true,
            'data' => $users,
            'count' => count($users),
        ]);
    }

    public function getUser(Request $request): Response
    {
        $userId = (int)($request->parameters['id'] ?? 0);

        if ($userId <= 0) {
            return new Response([
                'success' => false,
                'error' => 'Invalid user ID',
            ], 400);
        }

        $user = $this->userService->findById($userId);

        if ($user === null) {
            return new Response([
                'success' => false,
                'error' => 'User not found',
            ], 404);
        }

        return new Response([
            'success' => true,
            'data' => $user,
        ]);
    }
}

// Bootstrap application
echo "Bootstrapping application...\n";

$router = new Router();
$userService = new UserService();
$userController = new UserController($userService);

// Register routes
$router->addRoute('/api/users', 'GET', [$userController, 'listUsers']);
$router->addRoute('/api/users/1', 'GET', [$userController, 'getUser']);

echo "Routes registered.\n\n";

// Simulate requests
$requests = [
    new Request('GET', '/api/users'),
    new Request('GET', '/api/users/1', ['id' => 1]),
];

foreach ($requests as $i => $request) {
    echo "=== Request " . ($i + 1) . ": {$request->method} {$request->path} ===\n";

    $handler = $router->match($request);

    if ($handler === null) {
        $response = new Response(['error' => 'Not found'], 404);
    } else {
        $response = $handler($request);
    }

    $response->send();
    echo "\n";
}

echo "=== Simulation Complete ===\n";
