<?php
echo 'Step 1: PHP works<br>';
echo 'Step 2: cURL module: ' . (function_exists('curl_init') ? 'YES' : 'NO') . '<br>';
$ch = curl_init('http://127.0.0.1:3000/api/health');
echo 'Step 3: cURL initialized<br>';
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
$error = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo 'Step 4: HTTP Code: ' . $httpCode . '<br>';
echo 'Step 5: Error: ' . ($error ? $error : 'none') . '<br>';
echo 'Step 6: Response: ' . htmlspecialchars($response) . '<br>';
?>
