<?php
/**
 * CoinGate Service
 * Handles CoinGate payment order creation and status
 * API Docs: https://developer.coingate.com/docs/create-order
 */
class CoinGateService {
    private $apiToken;
    private $apiUrl;
    private $environment;

    public function __construct() {
        $this->apiToken = defined('COINGATE_API_TOKEN') ? COINGATE_API_TOKEN : getenv('COINGATE_API_TOKEN');
        
        // Determine environment (sandbox or production)
        $this->environment = getenv('COINGATE_ENV') ?: 'live';
        
        // Set API URL based on environment
        if ($this->environment === 'sandbox') {
            $this->apiUrl = 'https://api-sandbox.coingate.com/v2';
        } else {
            $this->apiUrl = 'https://api.coingate.com/v2';
        }
        
        if (empty($this->apiToken)) {
            error_log("CoinGate API Token is not set!");
        }
        
        error_log("CoinGate initialized - Environment: {$this->environment}, URL: {$this->apiUrl}");
    }

    /**
     * Create CoinGate order (invoice)
     * API Docs: https://developer.coingate.com/reference/create-order
     */
    public function createOrder($paymentId, $amount, $currency = 'USD', $callbackUrl = '', $successUrl = '', $cancelUrl = '', $receiveCurrency = 'BTC') {
        // Correct API endpoint
        $endpoint = $this->apiUrl . '/orders';
        
        // Validate required fields
        if (empty($amount) || $amount <= 0) {
            return [
                'success' => false,
                'error' => 'Invalid amount: ' . $amount
            ];
        }
        
        // Build request data according to CoinGate API specifications
        $data = [
            // Required fields
            'price_amount' => floatval($amount),
            'price_currency' => strtoupper($currency),
            'receive_currency' => strtoupper($receiveCurrency),
        ];
        
        // Optional fields - add only if provided
        if (!empty($paymentId)) {
            $data['order_id'] = (string)$paymentId;
        }
        
        if (!empty($callbackUrl)) {
            $data['callback_url'] = $callbackUrl;
        }
        
        if (!empty($successUrl)) {
            $data['success_url'] = $successUrl;
        }
        
        if (!empty($cancelUrl)) {
            $data['cancel_url'] = $cancelUrl;
        }
        
        // Optional: Add title and description for better order identification
        $data['title'] = 'Payment ' . $paymentId;
        $data['description'] = 'Payment for order ' . $paymentId;
        
        // Set headers according to CoinGate API documentation
        $headers = [
            'Authorization: Token ' . $this->apiToken,
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        
        // Log the request for debugging
        error_log("CoinGate Create Order Request - URL: {$endpoint}");
        error_log("CoinGate Create Order Data: " . json_encode($data));
        
        // Initialize cURL
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        // Execute request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        // Decode response
        $result = json_decode($response, true);
        
        // Log the full response for debugging
        error_log("CoinGate API Response - HTTP {$httpCode}: " . $response);
        
        // CoinGate returns 200 OK on success (not 201)
        if ($httpCode === 200 && isset($result['payment_url'])) {
            return [
                'success' => true,
                'payment_url' => $result['payment_url'],
                'order_id' => $result['id'],
                'coingate_status' => $result['status'],
            ];
        } else {
            // Extract error message from CoinGate response
            $errorMsg = 'CoinGate order creation failed';
            
            if (isset($result['message'])) {
                $errorMsg = $result['message'];
            } elseif (isset($result['reason'])) {
                $errorMsg = $result['reason'];
            } elseif (isset($result['errors']) && is_array($result['errors'])) {
                // Handle validation errors
                $errors = [];
                foreach ($result['errors'] as $field => $messages) {
                    if (is_array($messages)) {
                        $errors[] = $field . ': ' . implode(', ', $messages);
                    } else {
                        $errors[] = $field . ': ' . $messages;
                    }
                }
                $errorMsg = implode('; ', $errors);
            }
            
            if ($curlError) {
                $errorMsg .= ' (cURL: ' . $curlError . ')';
            }
            
            error_log("CoinGate Error - HTTP {$httpCode}: {$errorMsg}");
            error_log("CoinGate Full Response: " . print_r($result, true));
            
            return [
                'success' => false,
                'error' => $errorMsg,
                'http_code' => $httpCode,
                'response' => $result,
            ];
        }
    }
}
