<?php
/**
 * Debug script to test upload_chunk endpoint
 */

// Enable full error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log function
function debug_log($message, $data = []) {
    $log = date('Y-m-d H:i:s') . " - " . $message;
    if (!empty($data)) {
        $log .= " - " . json_encode($data);
    }
    $log .= "\n";
    file_put_contents('/var/www/html/kapjus.kaponline.com.br/upload_debug.log', $log, FILE_APPEND);
}

debug_log("=== NEW REQUEST ===");
debug_log("REQUEST_URI", ['uri' => $_SERVER['REQUEST_URI'] ?? 'N/A']);
debug_log("REQUEST_METHOD", ['method' => $_SERVER['REQUEST_METHOD'] ?? 'N/A']);
debug_log("CONTENT_TYPE", ['type' => $_SERVER['CONTENT_TYPE'] ?? 'N/A']);

// Check if this is an upload_chunk request
$request = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($request, PHP_URL_PATH);

if (strpos($path, '/api/upload_chunk') === 0) {
    debug_log("DETECTED upload_chunk request");
    
    // Check $_FILES
    debug_log("FILES", ['files' => $_FILES]);
    
    // Check php://input
    $input = file_get_contents('php://input');
    debug_log("INPUT_LENGTH", ['length' => strlen($input)]);
    debug_log("INPUT_PREVIEW", ['preview' => substr($input, 0, 500)]);
    
    // Check GET params
    debug_log("GET", ['get' => $_GET]);
    
    // Check POST params
    debug_log("POST", ['post' => $_POST]);
    
    // Try to extract action from URL
    $action = str_replace('/api/', '', $path);
    debug_log("EXTRACTED_ACTION", ['action' => $action]);
    
    if ($action === 'upload_chunk') {
        // Check if we can parse multipart data
        if (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== false) {
            debug_log("MULTIPART_DETECTED");
            
            if (!empty($_FILES)) {
                debug_log("FILES_POPULATED", ['file_keys' => array_keys($_FILES)]);
            } else {
                debug_log("FILES_EMPTY - trying manual parse");
                
                // Try to parse multipart manually
                $boundary = '';
                if (preg_match('/boundary=(.*)$/', $_SERVER['CONTENT_TYPE'], $matches)) {
                    $boundary = $matches[1];
                    debug_log("BOUNDARY", ['boundary' => $boundary]);
                    
                    $parts = explode('--' . $boundary, $input);
                    foreach ($parts as $idx => $part) {
                        debug_log("PART_$idx", [
                            'length' => strlen($part),
                            'preview' => substr($part, 0, 200)
                        ]);
                    }
                }
            }
        }
    }
}
