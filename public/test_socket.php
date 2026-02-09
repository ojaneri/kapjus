<?php
$socket_path = "/var/www/html/kapjus.kaponline.com.br/socket/kapjus.sock";
$socket_url = "unix://" . $socket_path;

echo "Testing socket connection to: " . $socket_url . "\n";
echo "Socket exists: " . (file_exists($socket_path) ? "yes" : "no") . "\n";
echo "Socket readable: " . (is_readable($socket_path) ? "yes" : "no") . "\n";

// Test the socket connection
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 5,
        'ignore_errors' => true
    ]
]);

$response = @file_get_contents($socket_url . "/upload_status/test", false, $context);

echo "Response: " . ($response ?: "none") . "\n";
echo "Error: " . error_get_last()['message'] ?? "none" . "\n";
