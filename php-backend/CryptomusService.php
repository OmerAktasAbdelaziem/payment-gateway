<?php
/**
 * Cryptomus Payment Service
 * Handles Cryptomus payment integration
 * API Docs: https://doc.cryptomus.com/
 */
class CryptomusService {
    private $merchantId;
    private $paymentApiKey;
    private $payoutApiKey;
    private $apiUrl = 'https://api.cryptomus.com/v1';

    public function __construct() {
        $this->merchantId = defined('CRYPTOMUS_MERCHANT_ID') ? CRYPTOMUS_MERCHANT_ID : getenv('CRYPTOMUS_MERCHANT_ID');
        $this->paymentApiKey = defined('CRYPTOMUS_PAYMENT_API_KEY') ? CRYPTOMUS_PAYMENT_API_KEY : getenv('CRYPTOMUS_PAYMENT_API_KEY');
        $this->payoutApiKey = defined('CRYPTOMUS_PAYOUT_API_KEY') ? CRYPTOMUS_PAYOUT_API_KEY : getenv('CRYPTOMUS_PAYOUT_API_KEY');
        
        if (empty($this->merchantId) || empty($this->paymentApiKey)) {
            error_log("Cryptomus Merchant ID or Payment API Key is not set!");
        }
        
        error_log("Cryptomus initialized - Merchant ID: " . substr($this->merchantId, 0, 8) . "...");
    }
    
    /**
     * Generate signature for Cryptomus API
     * @param string $data JSON encoded data
     * @param string $apiKey API key to use
     * @return string MD5 hash
     */
    private function generateSignature($data, $apiKey) {
        return md5(base64_encode($data) . $apiKey);
    }
    
    /**
     * Create a payment invoice
     * @param string $orderId Unique order ID
     * @param float $amount Amount to charge
     * @param string $currency Currency code (USD, EUR, etc.)
     * @param string $callbackUrl Webhook URL
     * @param string $successUrl Success redirect URL
     * @param string $returnUrl Return URL
     * @return array Response from API
     */
    public function createInvoice($orderId, $amount, $currency = 'USD', $callbackUrl = '', $successUrl = '', $returnUrl = '') {
        $endpoint = $this->apiUrl . '/payment';
        
        $data = [
            'amount' => (string)$amount,
            'currency' => strtoupper($currency),
            'order_id' => (string)$orderId,
        ];
        
        // Add optional parameters
        if (!empty($callbackUrl)) {
            $data['url_callback'] = $callbackUrl;
        }
        
        if (!empty($successUrl)) {
            $data['url_success'] = $successUrl;
        }
        
        if (!empty($returnUrl)) {
            $data['url_return'] = $returnUrl;
        }
        
        // Encode data
        $jsonData = json_encode($data);
        
        // Generate signature
        $signature = $this->generateSignature($jsonData, $this->paymentApiKey);
        
        // Set headers
        $headers = [
            'merchant: ' . $this->merchantId,
            'sign: ' . $signature,
            'Content-Type: application/json',
        ];
        
        error_log("Creating Cryptomus invoice - Order ID: {$orderId}, Amount: {$amount} {$currency}");
        error_log("Request data: " . $jsonData);
        
        // Initialize cURL
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        // Execute request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        error_log("Cryptomus API response - HTTP {$httpCode}");
        error_log("Response: " . substr($response, 0, 500));
        
        if ($curlError) {
            error_log("Cryptomus API cURL error: {$curlError}");
            return [
                'success' => false,
                'error' => 'Connection error: ' . $curlError
            ];
        }
        
        $result = json_decode($response, true);
        
        // Check response
        if ($httpCode === 200 && isset($result['state']) && $result['state'] === 0 && isset($result['result']['url'])) {
            // Success
            return [
                'success' => true,
                'payment_url' => $result['result']['url'],
                'invoice_id' => $result['result']['uuid'],
                'order_id' => $result['result']['order_id'],
                'amount' => $result['result']['amount'],
                'currency' => $result['result']['currency'],
                'payment_status' => $result['result']['payment_status'],
                'expired_at' => $result['result']['expired_at'],
            ];
        }
        
        // Error handling
        $errorMessage = 'API request failed';
        if (isset($result['message'])) {
            $errorMessage = $result['message'];
        } elseif (isset($result['errors'])) {
            $errorMessage = json_encode($result['errors']);
        }
        
        return [
            'success' => false,
            'error' => $errorMessage,
            'http_code' => $httpCode,
            'response' => $result
        ];
    }
    
    /**
     * Verify webhook signature
     * @param array $data Webhook data
     * @param string $receivedSignature Signature from webhook header
     * @return bool True if signature is valid
     */
    public function verifyWebhookSignature($data, $receivedSignature) {
        $jsonData = json_encode($data);
        $calculatedSignature = $this->generateSignature($jsonData, $this->paymentApiKey);
        return hash_equals($calculatedSignature, $receivedSignature);
    }
}
