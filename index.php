<?php
/**
 * Payment Page Router - PHP Only
 * Handles /pay/{payment_id} routes
 */

// Get the request URI
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);

// Check if this is a /pay/ route
if (preg_match('#^/pay/([A-Z0-9_]+)$#', $path, $matches)) {
    $paymentId = $matches[1];
    
    // Serve the payment page HTML
    readfile(__DIR__ . '/pay.html');
    exit;
}

// Otherwise, redirect to admin login
header('Location: /admin-login.php');
exit;

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
