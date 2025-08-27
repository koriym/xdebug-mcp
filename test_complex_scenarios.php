<?php

class UserProcessor {
    private array $users = [];
    
    public function __construct() {
        $this->users = [
            ['id' => 1, 'name' => 'Alice', 'age' => 25, 'active' => true],
            ['id' => 2, 'name' => 'Bob', 'age' => 30, 'active' => false], 
            ['id' => 3, 'name' => 'Charlie', 'age' => 35, 'active' => true]
        ];
    }
    
    public function processUsers(): array {
        $results = [];                          // Line 15 - First breakpoint
        
        foreach ($this->users as $user) {
            if ($this->validateUser($user)) {   // Line 18 - Second breakpoint
                $processed = $this->transformUser($user);
                $results[] = $processed;        // Line 20 - Third breakpoint
            }
        }
        
        return $this->finalizeResults($results); // Line 24 - Fourth breakpoint
    }
    
    private function validateUser(array $user): bool {
        $isValid = isset($user['name']) && 
                   isset($user['age']) && 
                   $user['age'] > 18 &&          // Line 30 - Fifth breakpoint
                   $user['active'] === true;
        
        return $isValid;                        // Line 33 - Sixth breakpoint
    }
    
    private function transformUser(array $user): array {
        $transformed = [
            'display_name' => strtoupper($user['name']), // Line 38 - Seventh breakpoint
            'category' => $this->categorizeByAge($user['age']),
            'permissions' => $this->calculatePermissions($user)
        ];
        
        return $transformed;                    // Line 42 - Eighth breakpoint
    }
    
    private function categorizeByAge(int $age): string {
        if ($age < 30) {
            return 'young';                     // Line 47 - Ninth breakpoint
        } elseif ($age < 40) {
            return 'middle';                    // Line 49 - Tenth breakpoint
        } else {
            return 'senior';                    // Line 51 - Eleventh breakpoint
        }
    }
    
    private function calculatePermissions(array $user): array {
        $permissions = ['read'];                // Line 56 - Twelfth breakpoint
        
        if ($user['age'] >= 25) {
            $permissions[] = 'write';           // Line 59 - Thirteenth breakpoint
        }
        
        if ($user['age'] >= 30) {
            $permissions[] = 'admin';           // Line 63 - Fourteenth breakpoint
        }
        
        return $permissions;                    // Line 66 - Fifteenth breakpoint
    }
    
    private function finalizeResults(array $results): array {
        $stats = [
            'total_processed' => count($results), // Line 71 - Sixteenth breakpoint
            'processed_at' => date('Y-m-d H:i:s'),
            'results' => $results
        ];
        
        return $stats;                          // Line 76 - Seventeenth breakpoint
    }
}

function main() {
    echo "🚀 Starting complex user processing...\n";
    
    $processor = new UserProcessor();           // Line 82 - Eighteenth breakpoint
    $results = $processor->processUsers();
    
    echo "📊 Processing completed\n";
    echo "📈 Total users processed: " . $results['total_processed'] . "\n";
    
    foreach ($results['results'] as $result) {
        echo "👤 {$result['display_name']} ({$result['category']}) - Permissions: " . 
             implode(', ', $result['permissions']) . "\n"; // Line 89 - Nineteenth breakpoint
    }
    
    return $results;                            // Line 92 - Twentieth breakpoint
}

main();                                         // Line 95 - Twenty-first breakpoint
?>