<?php

// Intentional syntax error for testing
echo "This script has a syntax error";

function test_function() {
    $array = [1, 2, 3
    // Missing closing bracket ] - syntax error
    echo "This line will never execute";
}

test_function();