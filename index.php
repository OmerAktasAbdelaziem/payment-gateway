<?php
// PHP Proxy for Node.js Application
// Forwards all requests to Node.js backend on port 3000

$nodeUrl = 'http://127.0.0.1:3000';
$requestUri = $_SERVER['REQUEST_URI'];

// Build the target URL
$url = $nodeUrl . $requestUri;

// Initialize cURL
$ch = curl_init($url);

// Configure cURL
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, false);
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

// Forward headers (excluding Host)
$headers = [];
foreach (getallheaders() as $key => $value) {
    if (strtolower($key) !== 'host') {
        $headers[] = "$key: $value";
    }
}
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

// Execute the request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

// Check for cURL errors
if (curl_errno($ch)) {
    $error = curl_error($ch);
    curl_close($ch);
    http_response_code(503);
    echo 'Service Unavailable: ' . htmlspecialchars($error);
    exit;
}

curl_close($ch);

// Forward the response
http_response_code($httpCode);
if ($contentType) {
    header('Content-Type: ' . $contentType);
}
echo $response;
?>
