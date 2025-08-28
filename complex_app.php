<?php

declare(strict_types=1);

/**
 * Complex PHP Application for Coverage Testing
 * Features: Classes, interfaces, traits, conditions, loops, exceptions
 */

interface LoggerInterface
{
    public function log(string $level, string $message): void;
}

trait ValidationTrait
{
    protected function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    protected function validateAge(int $age): bool
    {
        return $age >= 18 && $age <= 120;
    }
}

class FileLogger implements LoggerInterface
{
    private string $logFile;

    public function __construct(string $logFile = '/tmp/app.log')
    {
        $this->logFile = $logFile;
    }

    public function log(string $level, string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] {$level}: {$message}\n";
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

class User
{
    use ValidationTrait;

    private int $id;
    private string $name;
    private string $email;
    private int $age;
    private array $permissions = [];
    private LoggerInterface $logger;

    public function __construct(int $id, string $name, string $email, int $age, LoggerInterface $logger)
    {
        if (!$this->validateEmail($email)) {
            throw new InvalidArgumentException("Invalid email: {$email}");
        }
        
        if (!$this->validateAge($age)) {
            throw new InvalidArgumentException("Invalid age: {$age}");
        }

        $this->id = $id;
        $this->name = $name;
        $this->email = $email;
        $this->age = $age;
        $this->logger = $logger;
        
        $this->logger->log('info', "User created: {$name} ({$email})");
    }

    public function addPermission(string $permission): void
    {
        if (in_array($permission, $this->permissions)) {
            $this->logger->log('warning', "Permission '{$permission}' already exists for user {$this->name}");
            return;
        }
        
        $this->permissions[] = $permission;
        $this->logger->log('info', "Permission '{$permission}' added to user {$this->name}");
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions);
    }

    public function calculateDiscount(): float
    {
        $baseDiscount = 0.0;
        
        // Age-based discount
        if ($this->age >= 65) {
            $baseDiscount += 0.15; // Senior discount
        } elseif ($this->age <= 25) {
            $baseDiscount += 0.10; // Young adult discount
        }
        
        // Permission-based discount
        if ($this->hasPermission('premium')) {
            $baseDiscount += 0.20;
        } elseif ($this->hasPermission('member')) {
            $baseDiscount += 0.05;
        }
        
        // Cap at 50% discount
        return min($baseDiscount, 0.50);
    }

    public function processOrder(float $amount): array
    {
        try {
            if ($amount <= 0) {
                throw new InvalidArgumentException('Order amount must be positive');
            }
            
            $discount = $this->calculateDiscount();
            $discountAmount = $amount * $discount;
            $finalAmount = $amount - $discountAmount;
            
            $this->logger->log('info', "Order processed for {$this->name}: \${$amount} -> \${$finalAmount} (discount: {$discount})");
            
            return [
                'user_id' => $this->id,
                'original_amount' => $amount,
                'discount_rate' => $discount,
                'discount_amount' => $discountAmount,
                'final_amount' => $finalAmount,
                'status' => 'completed'
            ];
            
        } catch (Exception $e) {
            $this->logger->log('error', "Order processing failed for {$this->name}: " . $e->getMessage());
            return [
                'user_id' => $this->id,
                'error' => $e->getMessage(),
                'status' => 'failed'
            ];
        }
    }
}

class OrderProcessor
{
    private LoggerInterface $logger;
    private array $users = [];
    private array $orders = [];

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function addUser(User $user): void
    {
        $this->users[] = $user;
        $this->logger->log('info', 'User added to processor');
    }

    public function processAllOrders(array $orderData): array
    {
        $results = [];
        
        foreach ($orderData as $order) {
            if (!isset($order['user_index']) || !isset($order['amount'])) {
                $this->logger->log('warning', 'Invalid order data: missing user_index or amount');
                continue;
            }
            
            $userIndex = $order['user_index'];
            if (!isset($this->users[$userIndex])) {
                $this->logger->log('error', "User index {$userIndex} not found");
                continue;
            }
            
            $result = $this->users[$userIndex]->processOrder($order['amount']);
            $results[] = $result;
            $this->orders[] = $result;
        }
        
        return $results;
    }

    public function generateReport(): array
    {
        $totalOrders = count($this->orders);
        $completedOrders = array_filter($this->orders, fn($order) => $order['status'] === 'completed');
        $failedOrders = array_filter($this->orders, fn($order) => $order['status'] === 'failed');
        
        $totalRevenue = array_sum(array_column($completedOrders, 'final_amount'));
        $totalDiscounts = array_sum(array_column($completedOrders, 'discount_amount'));
        
        return [
            'total_orders' => $totalOrders,
            'completed_orders' => count($completedOrders),
            'failed_orders' => count($failedOrders),
            'total_revenue' => $totalRevenue,
            'total_discounts' => $totalDiscounts,
            'success_rate' => $totalOrders > 0 ? count($completedOrders) / $totalOrders : 0
        ];
    }
}

// Demo execution - Complex workflow
try {
    echo "🏪 Complex E-commerce Application Demo\n";
    echo "=====================================\n";
    
    $logger = new FileLogger('/tmp/complex_app.log');
    $processor = new OrderProcessor($logger);
    
    // Create users with different profiles
    $users = [
        new User(1, 'Alice Johnson', 'alice@example.com', 22, $logger),    // Young adult
        new User(2, 'Bob Smith', 'bob@example.com', 45, $logger),         // Middle-aged  
        new User(3, 'Carol Davis', 'carol@example.com', 70, $logger),     // Senior
    ];
    
    // Add permissions (some code paths will be tested, others not)
    $users[0]->addPermission('member');
    $users[1]->addPermission('premium');
    // Carol gets no special permissions
    
    foreach ($users as $user) {
        $processor->addUser($user);
    }
    
    // Process orders - this will test various discount calculations
    $orderData = [
        ['user_index' => 0, 'amount' => 100.00],  // Alice: young + member = 15% discount
        ['user_index' => 1, 'amount' => 200.00],  // Bob: premium = 20% discount  
        ['user_index' => 2, 'amount' => 150.00],  // Carol: senior = 15% discount
        ['user_index' => 0, 'amount' => 50.00],   // Alice again
        // Missing user test
        ['user_index' => 99, 'amount' => 100.00], // Invalid user index
        // Invalid amount test  
        ['user_index' => 1, 'amount' => -50.00],  // Negative amount
    ];
    
    echo "Processing orders...\n";
    $results = $processor->processAllOrders($orderData);
    
    foreach ($results as $i => $result) {
        echo "Order " . ($i + 1) . ": ";
        if ($result['status'] === 'completed') {
            echo "✅ \${$result['original_amount']} -> \${$result['final_amount']} ";
            echo "(discount: " . number_format($result['discount_rate'] * 100, 1) . "%)\n";
        } else {
            echo "❌ Failed: {$result['error']}\n";
        }
    }
    
    echo "\n📊 Final Report:\n";
    $report = $processor->generateReport();
    foreach ($report as $key => $value) {
        echo "  {$key}: " . (is_float($value) ? number_format($value, 2) : $value) . "\n";
    }
    
    echo "\n🎯 Coverage Test Complete!\n";
    
} catch (Exception $e) {
    echo "💥 Application Error: " . $e->getMessage() . "\n";
}

// Additional uncovered code paths for testing
function unusedFunction(): string
{
    return "This function is never called - should show in coverage as uncovered";
}

class UnusedClass
{
    public function method1(): void
    {
        echo "This will never execute\n";
    }
    
    private function method2(): int
    {
        return 42;
    }
}

// Conditional code that won't execute in our test
if (false) {
    echo "This block will never execute\n";
    $unused = new UnusedClass();
    $unused->method1();
}