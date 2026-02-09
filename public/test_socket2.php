<?php
$socket_path = "/var/www/html/kapjus.kaponline.com.br/socket/kapjus.sock";

echo "Testing stream_socket_client...\n";

// Create a Unix domain socket client
$socket = @stream_socket_client(
    "unix://" . $socket_path,
    $errno,
    $errstr,
    5,
    STREAM_CLIENT_CONNECT
);

if ($socket) {
    echo "Connected to socket!\n";
    
    // Send HTTP-like request
    $request = "GET /upload_status/test HTTP/1.1\r\n";
    $request .= "Host: localhost\r\n";
    $request .= "Connection: close\r\n\r\n";
    
    fwrite($socket, $request);
    
    // Read response
    $response = '';
    while (!feof($socket)) {
        $response .= fgets($socket, 4096);
    }
    
    fclose($socket);
    
    echo "Response:\n" . $response . "\n";
} else {
    echo "Failed to connect: [$errno] $errstr\n";
}
