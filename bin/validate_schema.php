<?php

require_once __DIR__ . '/../vendor/autoload.php';

use JsonSchema\Validator;
use JsonSchema\Constraints\Constraint;

if ($argc < 3) {
    echo "Usage: php validate_schema.php <schema_file> <data_file>\n";
    exit(1);
}

$schemaFile = $argv[1];
$dataFile = $argv[2];

try {
    // Load schema
    if (!file_exists($schemaFile)) {
        throw new Exception("Schema file not found: $schemaFile");
    }
    $schemaJson = file_get_contents($schemaFile);
    $schema = json_decode($schemaJson);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON in schema file: " . json_last_error_msg());
    }

    // Load data
    if (!file_exists($dataFile)) {
        throw new Exception("Data file not found: $dataFile");
    }
    $dataJson = file_get_contents($dataFile);
    $data = json_decode($dataJson);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON in data file: " . json_last_error_msg());
    }

    // Validate
    $validator = new Validator();
    $validator->validate($data, $schema, Constraint::CHECK_MODE_COERCE_TYPES);

    if ($validator->isValid()) {
        echo "✅ JSON Schema validation PASSED\n";
        exit(0);
    } else {
        echo "❌ JSON Schema validation FAILED\n";
        echo "Errors:\n";
        foreach ($validator->getErrors() as $error) {
            printf("  [%s] %s\n", $error['property'], $error['message']);
        }
        exit(1);
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}