<?php
// PHP Proxy for Node.js Application
// Forwards all requests to Node.js backend on port 3000

// Error reporting for debugging (disable in production if not needed)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$nodeUrl = 'http://127.0.0.1:3000';
$requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';

// Build the target URL
$url = $nodeUrl . $requestUri;

// Initialize cURL
$ch = curl_init($url);

if ($ch === false) {
    http_response_code(500);
    echo 'Failed to initialize cURL';
    exit;
}

// Configure cURL to capture headers
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);  // Include headers in output
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

// Forward the request method
$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

// Forward POST/PUT data if present
if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
    $postData = file_get_contents('php://input');
    if ($postData) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    }
}

// Forward headers (including cookies) - use fallback if getallheaders() not available
$headers = [];
if (function_exists('getallheaders')) {
    foreach (getallheaders() as $key => $value) {
        if (strtolower($key) !== 'host') {
            $headers[] = "$key: $value";
        }
    }
} else {
    // Fallback for servers without getallheaders()
    foreach ($_SERVER as $key => $value) {
        if (substr($key, 0, 5) === 'HTTP_') {
            $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
            if (strtolower($header) !== 'host') {
                $headers[] = "$header: $value";
            }
        }
    }
}

// Add X-Forwarded headers for proxy
$headers[] = 'X-Forwarded-For: ' . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1');
$headers[] = 'X-Forwarded-Proto: https';
$headers[] = 'X-Forwarded-Host: ' . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'gateway.internationalitpro.com');

curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

// Execute the request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// Check for cURL errors
if (curl_errno($ch)) {
    $error = curl_error($ch);
    curl_close($ch);
    http_response_code(503);
    echo 'Service Unavailable: ' . htmlspecialchars($error);
    exit;
}

// Split headers and body
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$header_text = substr($response, 0, $header_size);
$body = substr($response, $header_size);

curl_close($ch);

// Parse and forward response headers
$headers = explode("\r\n", $header_text);
foreach ($headers as $header) {
    $header = trim($header);
    if (!empty($header) && strpos($header, 'HTTP/') !== 0 && strpos($header, 'Transfer-Encoding') === false) {
        header($header, false);
    }
}

// Set HTTP response code
http_response_code($httpCode);

// Output the body
echo $body;
?>
