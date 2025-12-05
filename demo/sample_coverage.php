<?php

declare(strict_types=1);

/**
 * Sample Code Coverage Demo
 *
 * This script demonstrates code coverage analysis with xcoverage.
 * Some branches are intentionally NOT executed to show uncovered lines.
 *
 * Usage:
 *   ./bin/xcoverage -- php demo/sample_coverage.php
 */

class UserValidator
{
    public function validateAge(int $age): string
    {
        if ($age < 0) {
            // This branch will NOT be tested
            return 'invalid';
        }

        if ($age < 13) {
            return 'child';
        }

        if ($age < 20) {
            return 'teenager';
        }

        if ($age < 65) {
            return 'adult';
        }

        // This branch will NOT be tested
        return 'senior';
    }

    public function validateEmail(string $email): bool
    {
        if (empty($email)) {
            // This branch will NOT be tested
            return false;
        }

        if (!str_contains($email, '@')) {
            // This branch will NOT be tested
            return false;
        }

        return true;
    }

    public function validatePassword(string $password): array
    {
        $errors = [];

        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            // This branch will NOT be tested
            $errors[] = 'Password must contain uppercase letter';
        }

        if (!preg_match('/[0-9]/', $password)) {
            // This branch will NOT be tested
            $errors[] = 'Password must contain a number';
        }

        return $errors;
    }
}

function main(): void
{
    echo "=== Code Coverage Demo ===\n\n";

    $validator = new UserValidator();

    // Only testing some branches
    echo "Testing age validation:\n";
    echo "  Age 5: " . $validator->validateAge(5) . "\n";
    echo "  Age 15: " . $validator->validateAge(15) . "\n";
    echo "  Age 30: " . $validator->validateAge(30) . "\n";
    // NOT testing: negative age, senior (65+)

    echo "\nTesting email validation:\n";
    echo "  test@example.com: " . ($validator->validateEmail('test@example.com') ? 'valid' : 'invalid') . "\n";
    // NOT testing: empty email, email without @

    echo "\nTesting password validation:\n";
    $errors = $validator->validatePassword('SecurePass123');
    echo "  'SecurePass123': " . (empty($errors) ? 'valid' : implode(', ', $errors)) . "\n";
    // NOT testing: short password, no uppercase, no number

    echo "\nNote: Run with xcoverage to see which lines were NOT executed.\n";
}

main();
