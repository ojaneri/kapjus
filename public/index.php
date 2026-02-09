<?php
/**
 * KapJus - Sistema de Gestão Jurídica Inteligente
 * Single Entry Point with Apache Routing and Python Service Management
 */

// ============================================================================
// CONFIGURATION
// ============================================================================
define('BASE_DIR', '/var/www/html/kapjus.kaponline.com.br');
define('SOCKET_PATH', BASE_DIR . '/socket/kapjus.sock');
define('DEBUG_FILE', BASE_DIR . '/debug');
define('DEBUG_LOG', BASE_DIR . '/debug.log');
define('DB_PATH', BASE_DIR . '/database/kapjus.db');
define('PYTHON_PROCESSOR', BASE_DIR . '/src/python/processor.py');

// ============================================================================
// DEBUG LOGGING
// ============================================================================
$debug_enabled = file_exists(DEBUG_FILE);

function debug_log($level, $message, $context = []) {
    if (!$GLOBALS['debug_enabled']) return;
    
    $timestamp = date('Y-m-d H:i:s');
    $context_str = !empty($context) ? ' ' . json_encode($context) : '';
    $log_entry = "[{$timestamp}] [{$level}] {$message}{$context_str}\n";
    file_put_contents(DEBUG_LOG, $log_entry, FILE_APPEND | LOCK_EX);
}

// ============================================================================
// ERROR HANDLING
// ============================================================================
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    debug_log('ERROR', "PHP Error {$errstr} in {$errfile}:{$errline}");
    return false;
});

set_exception_handler(function($e) {
    debug_log('EXCEPTION', $e->getMessage(), ['trace' => $e->getTraceAsString()]);
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Internal server error']);
    exit;
});

// ============================================================================
// PYTHON SERVICE MANAGEMENT
// ============================================================================
function is_socket_available($path, $timeout = 1) {
    if (!file_exists($path)) {
        debug_log('DEBUG', "Socket file does not exist: {$path}");
        return false;
    }
    
    // Check if socket is readable and not stale
    if (!is_readable($path)) {
        debug_log('DEBUG', "Socket file is not readable: {$path}");
        return false;
    }
    
    // Try to connect to verify it's active
    $socket = @stream_socket_client('unix://' . $path, $errno, $errstr, $timeout);
    if ($socket === false) {
        debug_log('DEBUG', "Cannot connect to socket: {$errstr}");
        return false;
    }
    fclose($socket);
    
    return true;
}

function start_python_service() {
    debug_log('INFO', 'Starting Python service...');
    
    $command = 'cd ' . escapeshellarg(BASE_DIR) . ' && python3 ' . escapeshellarg(PYTHON_PROCESSOR) . ' > /dev/null 2>&1 & echo $!';
    $pid = shell_exec($command);
    
    if (!$pid || !is_numeric(trim($pid))) {
        debug_log('ERROR', 'Failed to start Python service');
        return false;
    }
    
    debug_log('INFO', "Python service started with PID: {$pid}");
    return true;
}

function ensure_python_service_running($max_retries = 5, $retry_delay = 1) {
    debug_log('INFO', 'Checking Python service status...');
    
    for ($i = 0; $i < $max_retries; $i++) {
        if (is_socket_available(SOCKET_PATH)) {
            debug_log('DEBUG', "Socket available on attempt " . ($i + 1));
            return true;
        }
        
        if ($i === 0) {
            // First check - try to start the service
            start_python_service();
        }
        
        debug_log('DEBUG', "Waiting for socket... attempt " . ($i + 1) . "/{$max_retries}");
        sleep($retry_delay);
    }
    
    debug_log('ERROR', 'Failed to start Python service after ' . $max_retries . ' retries');
    return false;
}

// ============================================================================
// SOCKET API CLIENT
// ============================================================================
function call_python_api($endpoint, $data = [], $is_json = true, $files = []) {
    $socket_path = SOCKET_PATH;
    
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
    
    if (empty($body)) {
        return json_encode(['error' => 'Empty response from socket']);
    }
    
    return $body;
}

// ============================================================================
// DATABASE INITIALIZATION
// ============================================================================
function initialize_database() {
    debug_log('DEBUG', 'Initializing database...');
    
    $db = new SQLite3(DB_PATH);
    
    // Create cases table
    $db->exec("CREATE TABLE IF NOT EXISTS cases (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        description TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Create lawyers table
    $db->exec("CREATE TABLE IF NOT EXISTS lawyers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        case_id INTEGER,
        email TEXT NOT NULL,
        token TEXT,
        FOREIGN KEY(case_id) REFERENCES cases(id)
    )");
    
    // Create documents table for PDF processing
    $db->exec("CREATE TABLE IF NOT EXISTS documents (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        case_id TEXT,
        filename TEXT,
        page_number INTEGER,
        content TEXT
    )");
    
    // Create FTS5 virtual table for full-text search
    $db->exec("CREATE VIRTUAL TABLE IF NOT EXISTS documents_fts USING fts5(content, case_id UNINDEXED, filename UNINDEXED, page_number UNINDEXED)");
    
    debug_log('DEBUG', 'Database initialized successfully');
    return $db;
}

// ============================================================================
// ROUTING HELPERS
// ============================================================================
function render_header($title = "KapJus") {
    echo '<!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    </head>
    <body class="bg-slate-50 text-slate-900 font-sans">';
}

function render_footer() {
    echo '</body></html>';
}

function show_error_page($message, $code = 500) {
    http_response_code($code);
    render_header("Erro - KapJus");
    echo '<div class="min-h-screen flex items-center justify-center bg-slate-50">
        <div class="text-center">
            <div class="w-24 h-24 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fas fa-exclamation-triangle text-4xl text-red-500"></i>
            </div>
            <h1 class="text-4xl font-black text-slate-900 mb-4">Ops! Algo deu errado</h1>
            <p class="text-slate-600 mb-8">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>
            <a href="/" class="inline-flex items-center px-6 py-3 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 transition-colors">
                <i class="fas fa-home mr-2"></i> Voltar ao Início
            </a>
        </div>
    </div>';
    render_footer();
    exit;
}

// ============================================================================
// MAIN EXECUTION
// ============================================================================
session_start();

// Ensure Python service is running
if (!ensure_python_service_running()) {
    show_error_page('Serviço de processamento não está respondendo. Por favor, tente novamente mais tarde.', 503);
}

// Initialize database
$db = initialize_database();

// Parse request URI
$request = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($request, PHP_URL_PATH);

// Route API requests
if (strpos($path, '/api/') === 0) {
    debug_log('INFO', 'API Request START', [
        'path' => $path, 
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
        'query_string' => $_SERVER['QUERY_STRING'] ?? '',
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'N/A',
        'content_length' => $_SERVER['CONTENT_LENGTH'] ?? 'N/A'
    ]);
    
    // Ensure socket is available for API requests
    if (!is_socket_available(SOCKET_PATH)) {
        if (!ensure_python_service_running()) {
            header('Content-Type: application/json');
            http_response_code(503);
            $error_response = json_encode(['error' => 'Python service unavailable']);
            debug_log('ERROR', 'Python service unavailable', ['response' => $error_response]);
            echo $error_response;
            exit;
        }
    }
    
    $_GET['action'] = str_replace('/api/', '', $path);
    
    // Special handling for upload_chunk - parse multipart data before including socket_client
    if ($_GET['action'] === 'upload_chunk') {
        debug_log('UPLOAD_CHUNK_REQUEST', [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'N/A',
            'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'N/A',
            'files_count' => count($_FILES),
            'post_count' => count($_POST)
        ]);
        
        // Ensure socket is available
        if (!is_socket_available(SOCKET_PATH)) {
            if (!ensure_python_service_running()) {
                header('Content-Type: application/json');
                http_response_code(503);
                echo json_encode(['error' => 'Python service unavailable']);
                exit;
            }
        }
        
        // Handle multipart upload_chunk request
        if (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== false) {
            $upload_id = $_POST['upload_id'] ?? '';
            $chunk_index = intval($_POST['chunk_index'] ?? 0);
            $filename = $_POST['filename'] ?? '';
            $case_id = $_POST['case_id'] ?? '';
            
            debug_log('UPLOAD_CHUNK_PARAMS', [
                'upload_id' => $upload_id,
                'chunk_index' => $chunk_index,
                'filename' => $filename,
                'case_id' => $case_id
            ]);
            
            if (isset($_FILES['chunk']) && $_FILES['chunk']['error'] === UPLOAD_ERR_OK) {
                $chunk_data = file_get_contents($_FILES['chunk']['tmp_name']);
                $files = [
                    'chunk' => [
                        'filename' => $_FILES['chunk']['name'],
                        'type' => $_FILES['chunk']['type'] ?? 'application/octet-stream',
                        'data' => $chunk_data
                    ]
                ];
                
                $data = [
                    'upload_id' => $upload_id,
                    'chunk_index' => $chunk_index,
                    'filename' => $filename,
                    'case_id' => $case_id
                ];
                
                // Call Python API directly
                $response = call_python_api('/upload_chunk', $data, false, $files);
                debug_log('UPLOAD_CHUNK_RESPONSE', ['response' => substr($response, 0, 500)]);
                echo $response;
                exit;
            } else {
                // Try to parse raw multipart data
                $input = file_get_contents('php://input');
                if (preg_match('/boundary=(.*)$/', $_SERVER['CONTENT_TYPE'], $matches)) {
                    $boundary = $matches[1];
                    $chunk_data = null;
                    
                    foreach (explode('--' . $boundary, $input) as $part) {
                        if (strpos($part, 'Content-Disposition:') !== false && strpos($part, 'name="chunk"') !== false) {
                            $chunk_start = strpos($part, "\r\n\r\n");
                            if ($chunk_start !== false) {
                                $chunk_data = substr($part, $chunk_start + 4);
                                $chunk_data = preg_replace('/\r\n$/', '', $chunk_data);
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
                        
                        $response = call_python_api('/upload_chunk', $data, false, $files);
                        debug_log('UPLOAD_CHUNK_RAW_RESPONSE', ['response' => substr($response, 0, 500)]);
                        echo $response;
                        exit;
                    }
                }
                
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode(['error' => 'No chunk file uploaded', 'files' => $_FILES]);
                exit;
            }
        }
    }
    
    // Capture and log the response from socket_client.php
    try {
        ob_start();
        include BASE_DIR . '/src/php/socket_client.php';
        $api_response = ob_get_clean();
        
        if (empty($api_response)) {
            debug_log('ERROR', 'Empty response from socket_client.php', [
                'path' => $path,
                'action' => $_GET['action'] ?? 'N/A'
            ]);
            http_response_code(500);
            echo json_encode(['error' => 'Empty response from API']);
            exit;
        }
        
        debug_log('DEBUG', 'API Response', [
            'path' => $path,
            'action' => $_GET['action'] ?? 'N/A',
            'response_length' => strlen($api_response),
            'response_preview' => substr($api_response, 0, 200)
        ]);
        
        echo $api_response;
        exit;
    } catch (Exception $e) {
        ob_end_clean();
        debug_log('EXCEPTION', 'Error in API handling', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'path' => $path
        ]);
        http_response_code(500);
        echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
        exit;
    }
}

// Route page requests
if ($path === '/' || $path === '/index.php' || $path === '') {
    debug_log('INFO', 'Page Request: Home');
    render_header("KapJus - Início");
    include BASE_DIR . '/src/php/home.php';
    render_footer();
} elseif (preg_match('/^\/case\/(\d+)$/', $path, $matches)) {
    $case_id = (int)$matches[1];
    debug_log('INFO', 'Page Request: Case Detail', ['case_id' => $case_id]);
    
    // Verify case exists
    $stmt = $db->prepare("SELECT id FROM cases WHERE id = :id");
    $stmt->bindValue(':id', $case_id, SQLITE3_INTEGER);
    $result = $stmt->execute()->fetchArray();
    
    if (!$result) {
        show_error_page('Caso não encontrado', 404);
    }
    
    render_header("Caso #{$case_id} - KapJus");
    include BASE_DIR . '/src/php/case_detail.php';
    render_footer();
} else {
    debug_log('INFO', 'Page Request: 404', ['path' => $path]);
    show_error_page('Página não encontrada', 404);
}
