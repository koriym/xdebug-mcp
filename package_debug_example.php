<?php
/**
 * Package Usage Example: Breakpoint + Step Debugging
 * 
 * This demonstrates how to use xdebug-mcp as a composer package
 * for interactive debugging with breakpoints and step control.
 */

function calculateTax($price, $rate = 0.10) {
    $tax = $price * $rate;        // Line 11 - Good breakpoint location
    $total = $price + $tax;       // Line 12 - Step debugging target
    return $total;
}

function processOrder($items) {
    $subtotal = 0;
    foreach ($items as $item) {   // Line 17 - Loop breakpoint
        $subtotal += $item['price'] * $item['qty'];
    }
    
    $tax = calculateTax($subtotal, 0.08);  // Line 21 - Function call breakpoint
    $shipping = $subtotal > 100 ? 0 : 9.99;
    
    return [
        'subtotal' => $subtotal,
        'tax' => $tax - $subtotal,  // Line 26 - Step target
        'shipping' => $shipping,
        'total' => $tax + $shipping
    ];
}

// Test data for debugging
$orderItems = [
    ['name' => 'Widget A', 'price' => 25.50, 'qty' => 2],
    ['name' => 'Widget B', 'price' => 15.00, 'qty' => 1],
    ['name' => 'Widget C', 'price' => 45.99, 'qty' => 1]
];

echo "Processing order with " . count($orderItems) . " items...\n";
$result = processOrder($orderItems);

echo "Order Summary:\n";
print_r($result);