<?php
define('BASE_DIR', '/var/www/html/kapjus.kaponline.com.br');
define('SOCKET_PATH', BASE_DIR . '/socket/kapjus.sock');
define('DEBUG_FILE', BASE_DIR . '/debug');
define('DEBUG_LOG', BASE_DIR . '/debug.log');
define('DB_PATH', BASE_DIR . '/database/kapjus.db');
define('PYTHON_PROCESSOR', BASE_DIR . '/src/python/processor.py');

function is_socket_available_test($path, $timeout = 1) {
    if (!file_exists($path)) return false;
    $socket = @stream_socket_client('unix://' . $path, $errno, $errstr, $timeout);
    if ($socket === false) return false;
    fclose($socket);
    return true;
}

function start_python_service_test() {
    $command = 'cd ' . escapeshellarg(BASE_DIR) . ' && python3 ' . escapeshellarg(PYTHON_PROCESSOR) . ' > /dev/null 2>&1 & echo $!';
    $pid = shell_exec($command);
    return $pid;
}

function ensure_python_service_running_test($max_retries = 5, $retry_delay = 1) {
    for ($i = 0; $i < $max_retries; $i++) {
        if (is_socket_available_test(SOCKET_PATH)) return true;
        if ($i === 0) start_python_service_test();
        sleep($retry_delay);
    }
    return false;
}

function call_python_api_test($endpoint, $data = []) {
    // Form data instead of JSON
    $post_data = http_build_query($data);
    $socket = @stream_socket_client("unix://" . SOCKET_PATH, $errno, $errstr, 5);
    if (!$socket) return "Socket failed: $errstr";
    
    $request = "POST $endpoint HTTP/1.1\r\nHost: localhost\r\nContent-Type: application/x-www-form-urlencoded\r\nContent-Length: " . strlen($post_data) . "\r\nConnection: close\r\n\r\n" . $post_data;
    fwrite($socket, $request);
    $response = '';
    while (!feof($socket)) $response .= fgets($socket, 4096);
    fclose($socket);
    $parts = explode("\r\n\r\n", $response, 2);
    return $parts[1] ?? $parts[0] ?? '';
}

echo "Ensuring service is running...\n";
if (ensure_python_service_running_test()) {
    echo "Service is running. Sending request...\n";
    $data = [
        'case_id' => '6',
        'question' => 'quem são os réus',
        'provider' => 'openrouter',
        'use_hybrid' => 'true' // Form data usually sends strings
    ];
    $response = call_python_api_test('/ask_ia', $data);
    echo "Response received:\n";
    echo $response . "\n";
} else {
    echo "Failed to start service.\n";
}
