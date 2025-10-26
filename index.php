<?php
// PHP Proxy for Node.js Application
// Forwards all requests to Node.js backend on port 3000

$nodeUrl = 'http://127.0.0.1:3000';
$requestUri = $_SERVER['REQUEST_URI'];

// Build the target URL
$url = $nodeUrl . $requestUri;

// Initialize cURL
$ch = curl_init($url);

// Configure cURL to capture headers
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);  // Include headers in output
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

// Forward the request method
$method = $_SERVER['REQUEST_METHOD'];
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

// Forward POST/PUT data if present
if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
    $postData = file_get_contents('php://input');
    if ($postData) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    }
}

// Forward headers (including cookies)
$headers = [];
foreach (getallheaders() as $key => $value) {
    if (strtolower($key) !== 'host') {
        $headers[] = "$key: $value";
    }
}

// Add X-Forwarded headers for proxy
$headers[] = 'X-Forwarded-For: ' . $_SERVER['REMOTE_ADDR'];
$headers[] = 'X-Forwarded-Proto: https';
$headers[] = 'X-Forwarded-Host: ' . $_SERVER['HTTP_HOST'];

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
