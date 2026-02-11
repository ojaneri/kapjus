<?php
/**
 * KAPJUS RAG - Socket Client for Python API Communication
 * Uses Unix sockets for PHP-FPM compatibility
 */

// Only declare function if not already defined (to avoid conflicts with index.php)
if (!function_exists('call_python_api')) {
    function call_python_api($endpoint, $data = [], $is_json = true, $files = []) {
    $socket_path = "/var/www/html/kapjus.kaponline.com.br/socket/kapjus.sock";
    
    if (!empty($files)) {
        // Multipart form data with files
        $boundary = '----WebKitFormBoundary' . bin2hex(random_bytes(16));
        $post_data = '';
        
        // Add regular fields
        foreach ($data as $key => $value) {
            $post_data .= "--$boundary\r\n";
            $post_data .= "Content-Disposition: form-data; name=\"$key\"\r\n\r\n";
            $post_data .= "$value\r\n";
        }
        
        // Add files
        foreach ($files as $key => $file) {
            $post_data .= "--$boundary\r\n";
            $post_data .= "Content-Disposition: form-data; name=\"$key\"; filename=\"{$file['filename']}\"\r\n";
            $post_data .= "Content-Type: {$file['type']}\r\n\r\n";
            $post_data .= "{$file['data']}\r\n";
        }
        
        $post_data .= "--$boundary--\r\n";
        $content_type = "multipart/form-data; boundary=$boundary";
    } elseif ($is_json) {
        $post_data = json_encode($data);
        $content_type = 'application/json';
    } else {
        $post_data = http_build_query($data);
        $content_type = 'application/x-www-form-urlencoded';
    }
    
    // Use stream_socket_client with Unix socket
    $socket = @stream_socket_client(
        "unix://" . $socket_path,
        $errno,
        $errstr,
        5,
        STREAM_CLIENT_CONNECT
    );
    
    if (!$socket) {
        return json_encode(['error' => "Socket connection failed: [$errno] $errstr"]);
    }
    
    // Build HTTP request for Unix socket
    $request = "POST $endpoint HTTP/1.1\r\n";
    $request .= "Host: localhost\r\n";
    $request .= "Content-Type: $content_type\r\n";
    $request .= "Content-Length: " . strlen($post_data) . "\r\n";
    $request .= "Connection: close\r\n\r\n";
    $request .= $post_data;
    
    fwrite($socket, $request);
    
    // Read response
    $response = '';
    while (!feof($socket)) {
        $response .= fgets($socket, 4096);
    }
    
    fclose($socket);
    
    // Parse HTTP response to get body
    $parts = explode("\r\n\r\n", $response, 2);
    $body = $parts[1] ?? $parts[0] ?? '';
    
    // Log the API call and response for debugging
    if (function_exists('debug_log')) {
        debug_log('DEBUG', "API call to {$endpoint}", [
            'response' => substr($body ?: '', 0, 500)
        ]);
    }
    
    if (empty($body)) {
        return json_encode(['error' => 'Empty response from socket']);
    }
    
    return $body;
    }
} // End of if (!function_exists())

// ==================== CHUNKED UPLOAD FUNCTIONS ====================

/**
 * Initialize a chunked upload session
 */
function init_upload($case_id, $filename, $total_size, $total_chunks) {
    $data = [
        'case_id' => $case_id,
        'filename' => $filename,
        'total_size' => $total_size,
        'total_chunks' => $total_chunks
    ];
    
    $response = call_python_api('/upload_init', $data);
    return json_decode($response, true);
}

/**
 * Upload a single chunk
 */
function upload_chunk($upload_id, $chunk_index, $chunk_data, $filename, $case_id) {
    $data = [
        'upload_id' => $upload_id,
        'chunk_index' => $chunk_index,
        'filename' => $filename,
        'case_id' => $case_id
    ];
    
    $files = [
        'chunk' => [
            'filename' => 'chunk.bin',
            'type' => 'application/octet-stream',
            'data' => $chunk_data
        ]
    ];
    
    $response = call_python_api('/upload_chunk', $data, false, $files);
    return json_decode($response, true);
}

/**
 * Complete a chunked upload
 */
function complete_upload($upload_id, $case_id) {
    $data = [
        'upload_id' => $upload_id,
        'case_id' => $case_id
    ];
    
    $response = call_python_api('/upload_complete', $data);
    return json_decode($response, true);
}

/**
 * Get upload status/progress using stream_socket_client
 */
function get_upload_status($upload_id) {
    $socket_path = "/var/www/html/kapjus.kaponline.com.br/socket/kapjus.sock";
    
    $socket = @stream_socket_client(
        "unix://" . $socket_path,
        $errno,
        $errstr,
        5,
        STREAM_CLIENT_CONNECT
    );
    
    if (!$socket) {
        return ['status' => 'error', 'message' => "Socket connection failed: [$errno] $errstr"];
    }
    
    $request = "GET /upload_status/" . $upload_id . " HTTP/1.1\r\n";
    $request .= "Host: localhost\r\n";
    $request .= "Connection: close\r\n\r\n";
    
    fwrite($socket, $request);
    
    $response = '';
    while (!feof($socket)) {
        $response .= fgets($socket, 4096);
    }
    
    fclose($socket);
    
    $parts = explode("\r\n\r\n", $response, 2);
    $body = $parts[1] ?? $parts[0] ?? '';
    
    if (empty($body)) {
        return ['status' => 'error', 'message' => 'Empty response from socket'];
    }
    
    return json_decode($body, true);
}

/**
 * Cancel an upload session using stream_socket_client
 */
function cancel_upload($upload_id) {
    $socket_path = "/var/www/html/kapjus.kaponline.com.br/socket/kapjus.sock";
    
    $socket = @stream_socket_client(
        "unix://" . $socket_path,
        $errno,
        $errstr,
        5,
        STREAM_CLIENT_CONNECT
    );
    
    if (!$socket) {
        return ['status' => 'error', 'message' => "Socket connection failed: [$errno] $errstr"];
    }
    
    $request = "DELETE /upload/" . $upload_id . " HTTP/1.1\r\n";
    $request .= "Host: localhost\r\n";
    $request .= "Connection: close\r\n\r\n";
    
    fwrite($socket, $request);
    
    $response = '';
    while (!feof($socket)) {
        $response .= fgets($socket, 4096);
    }
    
    fclose($socket);
    
    $parts = explode("\r\n\r\n", $response, 2);
    $body = $parts[1] ?? $parts[0] ?? '';
    
    if (empty($body)) {
        return ['status' => 'error', 'message' => 'Empty response from socket'];
    }
    
    return json_decode($body, true);
}

// ==================== LAWYER INVITATION FUNCTIONS ====================

/**
 * Send a lawyer invitation with magic link
 */
function invite_lawyer($case_id, $inviter_email, $invitee_email, $invitee_name = null, $role = 'viewer') {
    $data = [
        'case_id' => $case_id,
        'inviter_email' => $inviter_email,
        'invitee_email' => $invitee_email,
        'invitee_name' => $invitee_name,
        'role' => $role
    ];
    
    $response = call_python_api('/invite_lawyer', $data);
    return json_decode($response, true);
}

/**
 * Verify a magic link token
 */
function verify_invitation($token, $case_id) {
    $data = [
        'token' => $token,
        'case_id' => $case_id
    ];
    
    $response = call_python_api('/verify_invitation', $data);
    return json_decode($response, true);
}

/**
 * Revoke a pending invitation
 */
function revoke_invitation($invitation_id, $case_id) {
    $data = [
        'invitation_id' => $invitation_id,
        'case_id' => $case_id
    ];
    
    $response = call_python_api('/revoke_invitation', $data);
    return json_decode($response, true);
}

/**
 * List all invitations for a case
 */
function list_invitations($case_id) {
    $data = [
        'case_id' => $case_id
    ];
    
    $response = call_python_api('/invitations', $data);
    return json_decode($response, true);
}

// Handler para requisições AJAX do frontend
// Direct debug logging since index.php debug_log may not be available in this scope
$debug_file = '/var/www/html/kapjus.kaponline.com.br/debug.log';
$debug_enabled = file_exists('/var/www/html/kapjus.kaponline.com.br/debug');

function socket_debug_log($level, $message, $context = []) {
    if (!$GLOBALS['debug_enabled']) return;
    $timestamp = date('Y-m-d H:i:s');
    $context_str = !empty($context) ? ' ' . json_encode($context) : '';
    $log_entry = "[{$timestamp}] [{$level}] {$message}{$context_str}\n";
    @file_put_contents($GLOBALS['debug_file'], $log_entry, FILE_APPEND | LOCK_EX);
}

socket_debug_log('DEBUG', 'socket_client.php START', ['action' => $_GET['action'] ?? 'N/A']);

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    socket_debug_log('DEBUG', 'Processing action', ['action' => $action]);
    
    if ($action == 'search') {
        $input = json_decode(file_get_contents('php://input'), true);
        echo call_python_api('/search', $input);
    } elseif ($action == 'ask_ia') {
        echo call_python_api('/ask_ia', $_POST, false);
    } elseif ($action == 'upload') {
        if (isset($_FILES['file'])) {
            $data = [
                'case_id' => $_POST['case_id']
            ];
            echo call_python_api('/process_document', $data, false);
        }
    } elseif ($action == 'documents') {
        $input = json_decode(file_get_contents('php://input'), true);
        echo call_python_api('/documents', $input);
    } elseif ($action == 'delete_file') {
        $input = json_decode(file_get_contents('php://input'), true);
        echo call_python_api('/delete_file', $input);
    } elseif ($action == 'upload_init') {
        $input = json_decode(file_get_contents('php://input'), true);
        echo call_python_api('/upload_init', $input);
    } elseif ($action == 'upload_chunk') {
        // Handle chunk upload - parse multipart form data
        $upload_id = $_POST['upload_id'] ?? '';
        $chunk_index = intval($_POST['chunk_index'] ?? 0);
        $filename = $_POST['filename'] ?? '';
        $case_id = $_POST['case_id'] ?? '';
        
        $data = [
            'upload_id' => $upload_id,
            'chunk_index' => $chunk_index,
            'filename' => $filename,
            'case_id' => $case_id
        ];
        
        if (isset($_FILES['chunk']) && $_FILES['chunk']['error'] === UPLOAD_ERR_OK) {
            // File uploaded via PHP $_FILES
            $chunk_data = file_get_contents($_FILES['chunk']['tmp_name']);
            $files = [
                'chunk' => [
                    'filename' => $_FILES['chunk']['name'],
                    'type' => $_FILES['chunk']['type'] ?? 'application/octet-stream',
                    'data' => $chunk_data
                ]
            ];
            echo call_python_api('/upload_chunk', $data, false, $files);
        } else {
            // Fallback: try to parse raw multipart data from php://input
            $input = file_get_contents('php://input');
            if (preg_match('/boundary=(.*)$/', $_SERVER['CONTENT_TYPE'] ?? '', $matches)) {
                $boundary = $matches[1];
                $parts = explode('--' . $boundary, $input);
                $chunk_data = null;
                
                foreach ($parts as $part) {
                    if (strpos($part, 'Content-Disposition:') !== false) {
                        if (preg_match('/name="chunk"/', $part)) {
                            $chunk_start = strpos($part, "\r\n\r\n");
                            if ($chunk_start !== false) {
                                $chunk_data = substr($part, $chunk_start + 4);
                                // Remove trailing \r\n if present
                                $chunk_data = preg_replace('/\r\n$/', '', $chunk_data);
                            }
                        }
                    }
                }
                
                if ($chunk_data !== null) {
                    $files = [
                        'chunk' => [
                            'filename' => 'chunk.bin',
                            'type' => 'application/octet-stream',
                            'data' => $chunk_data
                        ]
                    ];
                    echo call_python_api('/upload_chunk', $data, false, $files);
                } else {
                    echo json_encode(['error' => 'No chunk data found in request']);
                }
            } else {
                echo json_encode(['error' => 'No chunk file uploaded', 'files' => $_FILES]);
            }
        }
    } elseif ($action == 'upload_complete') {
        $input = json_decode(file_get_contents('php://input'), true);
        echo call_python_api('/upload_complete', $input);
    } elseif ($action == 'upload_status') {
        $upload_id = $_GET['upload_id'] ?? '';
        echo json_encode(get_upload_status($upload_id));
    } elseif ($action == 'invite_lawyer') {
        $input = json_decode(file_get_contents('php://input'), true);
        echo call_python_api('/invite_lawyer', $input);
    } elseif ($action == 'verify_invitation') {
        $input = json_decode(file_get_contents('php://input'), true);
        echo call_python_api('/verify_invitation', $input);
    } elseif ($action == 'revoke_invitation') {
        $input = json_decode(file_get_contents('php://input'), true);
        echo call_python_api('/revoke_invitation', $input);
    } elseif ($action == 'invitations') {
        $input = json_decode(file_get_contents('php://input'), true);
        echo call_python_api('/invitations', $input);
    } elseif ($action == 'access_history') {
        $input = json_decode(file_get_contents('php://input'), true);
        echo call_python_api('/access_history', $input);
    }
    exit;
}
?>
