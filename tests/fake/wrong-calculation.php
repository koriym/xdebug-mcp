<?php

declare(strict_types=1);

/**
 * Wrong Calculation Logic - for debugging logic errors
 */
function calculateDiscount($price, $discountPercent) {
    echo "Calculating discount for price: $price, discount: $discountPercent%\n";
    
    // Convert percentage to decimal
    $discountDecimal = $discountPercent / 100;  // Line 15
    echo "Discount decimal: $discountDecimal\n";
    
    // Calculate discount amount
    $discountAmount = $price * $discountDecimal;
    echo "Discount amount: $discountAmount\n";
    
    // LOGIC ERROR: Should subtract discount, but accidentally adding it
    $finalPrice = $price + $discountAmount;  // Line 25 - BUG: Should be subtraction
    echo "Final price (WRONG): $finalPrice\n";
    
    return $finalPrice;
}

function calculateTax($price, $taxRate) {
    echo "Calculating tax for price: $price, tax rate: $taxRate%\n";
    
    $taxAmount = $price * ($taxRate / 100);
    echo "Tax amount: $taxAmount\n";
    
    // Correct calculation
    $finalPrice = $price + $taxAmount;
    echo "Price with tax: $finalPrice\n";
    
    return $finalPrice;
}

function processOrder($basePrice, $discountPercent, $taxRate) {
    echo "=== Processing Order ===\n";
    echo "Base price: $basePrice\n";
    
    // Apply discount (contains logic error)
    $discountedPrice = calculateDiscount($basePrice, $discountPercent);
    
    // Apply tax on discounted price
    $finalPrice = calculateTax($discountedPrice, $taxRate);
    
    echo "=== Final Order Total: $finalPrice ===\n";
    
    // Validation - this should catch the error
    $expectedDiscountedPrice = $basePrice - ($basePrice * $discountPercent / 100);
    $expectedFinalPrice = $expectedDiscountedPrice + ($expectedDiscountedPrice * $taxRate / 100);
    
    echo "Expected final price: $expectedFinalPrice\n";
    if (abs($finalPrice - $expectedFinalPrice) > 0.01) {
        echo "❌ CALCULATION ERROR DETECTED!\n";
    } else {
        echo "✅ Calculation verified\n";
    }
    
    return $finalPrice;
}

function main() {
    echo "🧮 Testing calculation logic\n";
    
    // Test case: $100 item with 10% discount and 8% tax
    // Expected: $100 - $10 = $90, then $90 + $7.20 = $97.20
    $result = processOrder(100.00, 10, 8);
    
    echo "\nFinal result: $" . number_format($result, 2) . "\n";
}

main();