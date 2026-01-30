<?php

/**
 * Test script for profiling/performance analysis in Docker
 *
 * Contains intentionally slow operations for profiling:
 * - Nested loops
 * - Recursive algorithms
 * - String operations
 * - Array manipulations
 */

declare(strict_types=1);

echo "=== Docker Performance Test Script ===\n\n";

/**
 * Inefficient prime number finder (for profiling demo)
 */
function findPrimes(int $limit): array
{
    $primes = [];

    for ($num = 2; $num <= $limit; $num++) {
        $isPrime = true;

        for ($i = 2; $i < $num; $i++) {
            if ($num % $i === 0) {
                $isPrime = false;
                break;
            }
        }

        if (! $isPrime) {
            continue;
        }

        $primes[] = $num;
    }

    return $primes;
}

/**
 * Bubble sort (intentionally O(n^2) for profiling)
 */
function bubbleSort(array $arr): array
{
    $n = count($arr);

    for ($i = 0; $i < $n - 1; $i++) {
        for ($j = 0; $j < $n - $i - 1; $j++) {
            if ($arr[$j] <= $arr[$j + 1]) {
                continue;
            }

            $temp = $arr[$j];
            $arr[$j] = $arr[$j + 1];
            $arr[$j + 1] = $temp;
        }
    }

    return $arr;
}

/**
 * String manipulation (memory intensive)
 */
function processStrings(int $count): array
{
    $results = [];

    for ($i = 0; $i < $count; $i++) {
        $str = str_repeat('a', 100);
        $str = strtoupper($str);
        $str = str_replace('A', 'B', $str);
        $str = substr($str, 0, 50);
        $results[] = md5($str);
    }

    return $results;
}

/**
 * Recursive tree traversal
 */
function buildTree(int $depth, int $breadth = 2): array
{
    if ($depth === 0) {
        return ['value' => rand(1, 100)];
    }

    $node = [
        'value' => rand(1, 100),
        'children' => [],
    ];

    for ($i = 0; $i < $breadth; $i++) {
        $node['children'][] = buildTree($depth - 1, $breadth);
    }

    return $node;
}

function sumTree(array $node): int
{
    $sum = $node['value'];

    if (isset($node['children'])) {
        foreach ($node['children'] as $child) {
            $sum += sumTree($child);
        }
    }

    return $sum;
}

// Run performance tests
$startTime = microtime(true);
$startMem = memory_get_usage(true);

echo "1. Finding primes up to 500:\n";
$t1 = microtime(true);
$primes = findPrimes(500);
$t1End = microtime(true);
echo '   Found ' . count($primes) . ' primes in ' . number_format(($t1End - $t1) * 1000, 2) . "ms\n\n";

echo "2. Bubble sorting 500 elements:\n";
$t2 = microtime(true);
$unsorted = range(1, 500);
shuffle($unsorted);
$sorted = bubbleSort($unsorted);
$t2End = microtime(true);
echo '   Sorted in ' . number_format(($t2End - $t2) * 1000, 2) . "ms\n\n";

echo "3. Processing 1000 strings:\n";
$t3 = microtime(true);
$strings = processStrings(1000);
$t3End = microtime(true);
echo '   Processed in ' . number_format(($t3End - $t3) * 1000, 2) . "ms\n\n";

echo "4. Building and summing tree (depth=8):\n";
$t4 = microtime(true);
$tree = buildTree(8);
$treeSum = sumTree($tree);
$t4End = microtime(true);
echo "   Tree sum: $treeSum in " . number_format(($t4End - $t4) * 1000, 2) . "ms\n\n";

$endTime = microtime(true);
$endMem = memory_get_usage(true);

echo "=== Performance Summary ===\n";
echo 'Total time: ' . number_format(($endTime - $startTime) * 1000, 2) . "ms\n";
echo 'Memory used: ' . number_format(($endMem - $startMem) / 1024) . " KB\n";
echo 'Peak memory: ' . number_format(memory_get_peak_usage(true) / 1024) . " KB\n";
