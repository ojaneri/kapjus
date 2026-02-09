<?php
// Test upload_init endpoint
require_once '/var/www/html/kapjus.kaponline.com.br/src/php/socket_client.php';

$test_data = [
    'case_id' => '999',
    'filename' => 'test_64mb.pdf',
    'total_size' => 67500000,
    'total_chunks' => 675
];

echo "Testing upload_init...\n";
$result = call_python_api('/upload_init', $test_data);
echo "Response: " . $result . "\n";
