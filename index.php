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
    // Set JSON header untuk semua API response
    header('Content-Type: application/json');
    
    // Extract API endpoint
    $apiPath = substr($requestPath, 4); // Remove 'api/' prefix
    
    // Remove query string if exists
    if (($pos = strpos($apiPath, '?')) !== false) {
        $apiPath = substr($apiPath, 0, $pos);
    }
    
    // Route to appropriate API file
    $apiFile = __DIR__ . '/api/' . $apiPath;
    
    // Ensure it's a PHP file
    if (pathinfo($apiFile, PATHINFO_EXTENSION) !== 'php') {
        $apiFile .= '.php';
    }
    
    if (file_exists($apiFile) && is_file($apiFile)) {
        // Capture any output buffer
        ob_start();
        try {
            require_once $apiFile;
            $output = ob_get_clean();
            
            // Check if output is valid JSON
            if (!empty($output)) {
                $jsonTest = json_decode($output, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    // Output bukan JSON, return error JSON
                    http_response_code(500);
                    echo json_encode([
                        'error' => 'Invalid API response',
                        'message' => 'API endpoint returned non-JSON response',
                        'raw_output' => substr(strip_tags($output), 0, 200) // First 200 chars for debugging
                    ]);
                } else {
                    // Valid JSON, output as is
                    echo $output;
                }
            }
        } catch (Throwable $e) {
            ob_end_clean();
            http_response_code(500);
            echo json_encode([
                'error' => 'API error',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
        exit;
    } else {
        http_response_code(404);
        echo json_encode([
            'error' => 'API endpoint not found',
            'path' => $apiPath
        ]);
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

