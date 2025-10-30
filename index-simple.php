<?php
// Simple PHP Proxy for Node.js - WORKING VERSION
$nodeUrl = 'http://127.0.0.1:3000';
$requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
$url = $nodeUrl . $requestUri;

// Initialize cURL
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

// Forward request method
$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

// Forward POST data
if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
    $postData = file_get_contents('php://input');
    if ($postData) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    }
}

// Forward headers
$headers = [];
foreach ($_SERVER as $key => $value) {
    if (substr($key, 0, 5) === 'HTTP_' && $key !== 'HTTP_HOST') {
        $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
        $headers[] = "$header: $value";
    }
}
$headers[] = 'X-Forwarded-For: ' . ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
$headers[] = 'X-Forwarded-Proto: https';
$headers[] = 'X-Forwarded-Host: ' . ($_SERVER['HTTP_HOST'] ?? 'gateway.internationalitpro.com');
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

// Execute
$response = curl_exec($ch);

// Check for errors
if (curl_errno($ch)) {
    $error = curl_error($ch);
    curl_close($ch);
    http_response_code(503);
    die('Service Unavailable: ' . htmlspecialchars($error));
}

// Get response info
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$header_text = substr($response, 0, $header_size);
$body = substr($response, $header_size);
curl_close($ch);

// Forward headers
foreach (explode("\r\n", $header_text) as $header) {
    $header = trim($header);
    if (!empty($header) && strpos($header, 'HTTP/') !== 0 && strpos($header, 'Transfer-Encoding') === false) {
        header($header, false);
    }
}

// Set status and output
http_response_code($httpCode);
echo $body;
?>
