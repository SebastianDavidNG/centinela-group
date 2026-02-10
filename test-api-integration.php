#!/usr/bin/env php
<?php
/**
 * Test script for API integration
 * 
 * This script tests the API service functionality without WordPress
 */

echo "=== Centinela Theme API Integration Test ===\n\n";

// Test 1: JSON API endpoint accessibility
echo "Test 1: Testing JSONPlaceholder API accessibility...\n";
$test_url = 'https://jsonplaceholder.typicode.com/posts?_limit=3';
$ch = curl_init($test_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 200) {
    echo "✓ API is accessible (HTTP 200)\n";
    $data = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "✓ JSON response is valid\n";
        echo "✓ Received " . count($data) . " items\n";
    } else {
        echo "✗ Failed to parse JSON: " . json_last_error_msg() . "\n";
    }
} else {
    echo "✗ API request failed with HTTP code: $http_code\n";
}

echo "\n";

// Test 2: Different endpoints
echo "Test 2: Testing different API endpoints...\n";
$endpoints = ['posts', 'users', 'comments'];
foreach ($endpoints as $endpoint) {
    $url = "https://jsonplaceholder.typicode.com/{$endpoint}?_limit=1";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        echo "✓ /$endpoint endpoint working\n";
    } else {
        echo "✗ /$endpoint endpoint failed (HTTP $http_code)\n";
    }
}

echo "\n";

// Test 3: Theme files exist
echo "Test 3: Checking theme files...\n";
$theme_path = __DIR__ . '/wp-content/themes/centinela-theme';
$required_files = [
    'style.css',
    'functions.php',
    'index.php',
    'header.php',
    'footer.php',
    'single.php',
    'page.php',
    'README.md'
];

$all_files_exist = true;
foreach ($required_files as $file) {
    $file_path = $theme_path . '/' . $file;
    if (file_exists($file_path)) {
        echo "✓ $file exists\n";
    } else {
        echo "✗ $file is missing\n";
        $all_files_exist = false;
    }
}

echo "\n";

// Test 4: PHP syntax check
echo "Test 4: Validating PHP syntax...\n";
$php_files = glob($theme_path . '/*.php');
$syntax_valid = true;
foreach ($php_files as $file) {
    $output = [];
    $return_var = 0;
    exec("php -l " . escapeshellarg($file) . " 2>&1", $output, $return_var);
    if ($return_var === 0) {
        echo "✓ " . basename($file) . " syntax valid\n";
    } else {
        echo "✗ " . basename($file) . " has syntax errors\n";
        $syntax_valid = false;
    }
}

echo "\n";

// Summary
echo "=== Test Summary ===\n";
if ($http_code === 200 && $all_files_exist && $syntax_valid) {
    echo "✓ All tests passed! Theme is ready to use.\n";
    echo "\nNext steps:\n";
    echo "1. Install WordPress\n";
    echo "2. Copy wp-content/themes/centinela-theme to your WordPress installation\n";
    echo "3. Activate the theme in WordPress admin\n";
    echo "4. Configure API settings in Appearance > API Settings\n";
    exit(0);
} else {
    echo "✗ Some tests failed. Please review the output above.\n";
    exit(1);
}
