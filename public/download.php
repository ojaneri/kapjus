<?php
/**
 * Secure file download controller.
 * Replaces direct Apache access to /storage/uploads/.
 * Validates session before serving the file.
 */

define('BASE_DIR', '/var/www/html/kapjus.kaponline.com.br');
define('UPLOAD_DIR', BASE_DIR . '/storage/uploads');

session_start();

// Must be authenticated
if (empty($_SESSION['kapjus_user'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Acesso não autorizado']);
    exit;
}

$filename = $_GET['file'] ?? '';

// Sanitize: no path traversal
$filename = basename($filename);
if (!$filename) {
    http_response_code(400);
    echo 'Arquivo não especificado.';
    exit;
}

$filepath = UPLOAD_DIR . '/' . $filename;

if (!file_exists($filepath) || !is_file($filepath)) {
    http_response_code(404);
    echo 'Arquivo não encontrado.';
    exit;
}

// Resolve real path and verify it's within UPLOAD_DIR (defense-in-depth)
$real = realpath($filepath);
$base = realpath(UPLOAD_DIR);
if (!$real || strpos($real, $base . DIRECTORY_SEPARATOR) !== 0) {
    http_response_code(403);
    echo 'Acesso negado.';
    exit;
}

// Detect MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $real) ?: 'application/octet-stream';
finfo_close($finfo);

// Send file
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($real));
header('Content-Disposition: inline; filename="' . addslashes($filename) . '"');
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');
readfile($real);
exit;
