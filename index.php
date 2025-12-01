<?php
/**
 * Main Entry Point untuk Vercel
 * File ini menangani semua routing
 */

// Get the request URI
$requestUri = $_SERVER['REQUEST_URI'];
$requestPath = parse_url($requestUri, PHP_URL_PATH);

// Remove leading slash
$requestPath = ltrim($requestPath, '/');

// Handle API routes
if (strpos($requestPath, 'api/') === 0) {
    // Extract API endpoint
    $apiPath = substr($requestPath, 4); // Remove 'api/' prefix
    
    // Route to appropriate API file
    $apiFile = __DIR__ . '/api/' . $apiPath . '.php';
    
    if (file_exists($apiFile)) {
        require_once $apiFile;
        exit;
    } else {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'API endpoint not found']);
        exit;
    }
}

// Handle static files (images, CSS, JS)
if (preg_match('/\.(jpg|jpeg|png|gif|css|js|ico|svg|woff|woff2|ttf|eot)$/i', $requestPath)) {
    $filePath = __DIR__ . '/' . $requestPath;
    if (file_exists($filePath)) {
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'ico' => 'image/x-icon',
            'svg' => 'image/svg+xml',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject'
        ];
        
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mimeType = $mimeTypes[$ext] ?? 'application/octet-stream';
        
        header('Content-Type: ' . $mimeType);
        readfile($filePath);
        exit;
    }
}

// Handle HTML pages
$htmlFile = __DIR__ . '/' . $requestPath;
if (file_exists($htmlFile) && pathinfo($htmlFile, PATHINFO_EXTENSION) === 'html') {
    readfile($htmlFile);
    exit;
}

// Default: serve index.html
if (empty($requestPath) || $requestPath === '/') {
    $indexFile = __DIR__ . '/index.html';
    if (file_exists($indexFile)) {
        readfile($indexFile);
        exit;
    }
}

// 404 Not Found
http_response_code(404);
header('Content-Type: text/html');
echo '<!DOCTYPE html><html><head><title>404 Not Found</title></head><body><h1>404 - Page Not Found</h1></body></html>';

