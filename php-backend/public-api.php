<?php
/**
 * Public Payment API - Get Payment Link Details
 * No authentication required
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/config.php';

$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));
$method = $_SERVER['REQUEST_METHOD'];

// Route: /api/public/payment/{payment_id}/processing - Update status to processing
if (isset($pathParts[3]) && isset($pathParts[4]) && $pathParts[4] === 'processing' && $method === 'POST') {
    $paymentId = $pathParts[3];
    
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            throw new Exception("Database connection failed");
        }
        
        // Update payment status to processing
        $stmt = $conn->prepare("UPDATE payment_links SET status = 'processing', updated_at = NOW() WHERE id = ? AND status = 'active'");
        $stmt->bind_param("s", $paymentId);
        $stmt->execute();
        $stmt->close();
        $conn->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'Status updated to processing'
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

// Route: /api/public/payment/{payment_id}
if (isset($pathParts[3]) && $pathParts[3]) {
    $paymentId = $pathParts[3];
    
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            throw new Exception("Database connection failed");
        }
        
        // Get payment link details
        $stmt = $conn->prepare("SELECT id, amount, currency, description, status FROM payment_links WHERE id = ? LIMIT 1");
        $stmt->bind_param("s", $paymentId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Payment link not found'
            ]);
            $stmt->close();
            $conn->close();
            exit;
        }
        
        $paymentLink = $result->fetch_assoc();
        $stmt->close();
        $conn->close();
        
        echo json_encode([
            'success' => true,
            'payment' => [
                'payment_id' => $paymentLink['id'],
                'amount' => floatval($paymentLink['amount']),
                'currency' => $paymentLink['currency'],
                'description' => $paymentLink['description'],
                'status' => $paymentLink['status']
            ]
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
} else {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error' => 'Payment ID required'
    ]);
}
