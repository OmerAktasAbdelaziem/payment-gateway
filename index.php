<?php
/**
 * Ultra-Simple Node.js Proxy
 * Forwards all requests to Node.js backend on port 3000
 */

// Configuration
define('NODE_HOST', '127.0.0.1');
define('NODE_PORT', '3000');

// Get request details
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];
$targetUrl = 'http://' . NODE_HOST . ':' . NODE_PORT . $uri;

// Initialize cURL
$ch = curl_init($targetUrl);
if (!$ch) {
    header('HTTP/1.1 503 Service Unavailable');
    die('Failed to initialize connection');
}

// Basic cURL options
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_CUSTOMREQUEST => $method
]);

// Forward POST/PUT/PATCH/DELETE data
if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
    $body = file_get_contents('php://input');
    if ($body) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
}

// Build headers array
$forwardHeaders = [];

// Forward all HTTP headers except Host
if (function_exists('getallheaders')) {
    $headers = getallheaders();
    foreach ($headers as $name => $value) {
        if (strtolower($name) !== 'host') {
            $forwardHeaders[] = "$name: $value";
        }
    }
}

// Add proxy headers
$forwardHeaders[] = 'X-Forwarded-For: ' . ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
$forwardHeaders[] = 'X-Forwarded-Proto: https';
$forwardHeaders[] = 'X-Forwarded-Host: ' . ($_SERVER['HTTP_HOST'] ?? 'gateway.internationalitpro.com');

curl_setopt($ch, CURLOPT_HTTPHEADER, $forwardHeaders);

// Execute request
$response = curl_exec($ch);

// Handle errors
if (curl_errno($ch)) {
    $error = curl_error($ch);
    curl_close($ch);
    header('HTTP/1.1 503 Service Unavailable');
    header('Content-Type: text/plain');
    die('Service Unavailable: ' . $error);
}

// Get response details
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

// Split headers and body
$responseHeaders = substr($response, 0, $headerSize);
$responseBody = substr($response, $headerSize);

curl_close($ch);

// Forward response headers
$headerLines = explode("\r\n", trim($responseHeaders));
foreach ($headerLines as $header) {
    $header = trim($header);
    // Skip HTTP status line and Transfer-Encoding
    if ($header && 
        strpos($header, 'HTTP/') !== 0 && 
        stripos($header, 'Transfer-Encoding:') !== 0) {
        header($header, false);
    }
}

// Set HTTP status code
http_response_code($statusCode);

// Output response body
echo $responseBody;
