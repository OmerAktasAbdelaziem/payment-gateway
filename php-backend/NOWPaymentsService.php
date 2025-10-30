<?php
/**
 * NOWPayments Service
 * Handles cryptocurrency payment processing
 * Documentation: https://documenter.getpostman.com/view/7907941/S1a32n38
 */

class NOWPaymentsService {
    private $apiKey;
    private $apiUrl = 'https://api.nowpayments.io/v1';
    private $ipnSecret;
    
    public function __construct() {
        $this->apiKey = NOWPAYMENTS_API_KEY;
        $this->ipnSecret = NOWPAYMENTS_IPN_SECRET;
    }
    
    /**
     * Create payment invoice
     * Customer can pay with card or crypto
     */
    public function createPayment($paymentId, $amount, $currency = 'usd', $description = '') {
        try {
            $endpoint = '/invoice';
            
            // Use configured payout currency (BTC or USDT)
            $payCurrency = defined('PAYOUT_CURRENCY') ? PAYOUT_CURRENCY : 'btc';
            
            $data = [
                'price_amount' => floatval($amount),
                'price_currency' => strtolower($currency),
                'pay_currency' => $payCurrency, // btc or usdttrc20
                'ipn_callback_url' => BASE_URL . '/api/nowpayments/webhook',
                'order_id' => $paymentId,
                'order_description' => $description ?: 'Payment',
                'success_url' => PAYMENT_SUCCESS_URL,
                'cancel_url' => PAYMENT_ERROR_URL,
            ];
            
            $response = $this->makeRequest($endpoint, 'POST', $data);
            
            return [
                'success' => true,
                'invoice_id' => $response['id'],
                'invoice_url' => $response['invoice_url'],
                'payment_id' => $paymentId,
                'amount' => $amount,
                'currency' => $currency,
                'pay_address' => $response['pay_address'] ?? null,
                'pay_amount' => $response['pay_amount'] ?? null,
                'pay_currency' => $payCurrency,
                'expires_at' => $response['created_at'] ?? null
            ];
            
        } catch (Exception $e) {
            error_log("NOWPayments create payment failed: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get payment status
     */
    public function getPaymentStatus($invoiceId) {
        try {
            $endpoint = '/invoice/' . $invoiceId;
            $response = $this->makeRequest($endpoint, 'GET');
            
            return [
                'success' => true,
                'status' => $this->mapStatus($response['payment_status']),
                'invoice_id' => $response['id'],
                'order_id' => $response['order_id'],
                'amount' => $response['price_amount'],
                'currency' => $response['price_currency'],
                'pay_amount' => $response['pay_amount'] ?? null,
                'pay_currency' => $response['pay_currency'] ?? null,
                'actually_paid' => $response['actually_paid'] ?? null,
                'payment_url' => $response['invoice_url'] ?? null
            ];
            
        } catch (Exception $e) {
            error_log("NOWPayments get status failed: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Handle IPN (Instant Payment Notification) webhook
     */
    public function handleWebhook($payload, $signature) {
        try {
            // Verify signature
            if (!$this->verifySignature($payload, $signature)) {
                throw new Exception("Invalid webhook signature");
            }
            
            $data = json_decode($payload, true);
            
            if (!isset($data['order_id']) || !isset($data['payment_status'])) {
                throw new Exception("Invalid webhook data");
            }
            
            $paymentId = $data['order_id'];
            $status = $this->mapStatus($data['payment_status']);
            
            error_log("NOWPayments webhook: Payment {$paymentId} status = {$status}");
            
            // Update payment in database
            require_once __DIR__ . '/Payment.php';
            $payment = new Payment();
            
            switch ($status) {
                case 'completed':
                    $payment->updateStatus($paymentId, 'completed', $data['payment_id'] ?? null);
                    error_log("Payment completed: {$paymentId}");
                    break;
                    
                case 'failed':
                    $payment->updateStatus($paymentId, 'failed', $data['payment_id'] ?? null);
                    error_log("Payment failed: {$paymentId}");
                    break;
                    
                case 'expired':
                    $payment->updateStatus($paymentId, 'canceled', $data['payment_id'] ?? null);
                    error_log("Payment expired: {$paymentId}");
                    break;
                    
                default:
                    // waiting, confirming, sending, etc.
                    error_log("Payment status update: {$paymentId} = {$status}");
            }
            
            return [
                'success' => true,
                'payment_id' => $paymentId,
                'status' => $status
            ];
            
        } catch (Exception $e) {
            error_log("NOWPayments webhook error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Verify webhook signature
     */
    private function verifySignature($payload, $signature) {
        if (!$this->ipnSecret) {
            error_log("Warning: IPN Secret not configured");
            return true; // Allow in dev, but log warning
        }
        
        $expectedSignature = hash_hmac('sha512', $payload, $this->ipnSecret);
        return hash_equals($expectedSignature, $signature);
    }
    
    /**
     * Map NOWPayments status to our internal status
     */
    private function mapStatus($nowpaymentsStatus) {
        $statusMap = [
            'waiting' => 'pending',
            'confirming' => 'processing',
            'confirmed' => 'processing',
            'sending' => 'processing',
            'partially_paid' => 'processing',
            'finished' => 'completed',
            'failed' => 'failed',
            'refunded' => 'refunded',
            'expired' => 'canceled'
        ];
        
        return $statusMap[strtolower($nowpaymentsStatus)] ?? 'pending';
    }
    
    /**
     * Get available currencies
     */
    public function getAvailableCurrencies() {
        try {
            $endpoint = '/currencies';
            $response = $this->makeRequest($endpoint, 'GET');
            
            return [
                'success' => true,
                'currencies' => $response['currencies'] ?? []
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get estimated price
     */
    public function getEstimatedPrice($amount, $fromCurrency = 'usd', $toCurrency = 'usdttrc20') {
        try {
            $endpoint = '/estimate';
            $params = [
                'amount' => $amount,
                'currency_from' => strtolower($fromCurrency),
                'currency_to' => strtolower($toCurrency)
            ];
            
            $url = $this->apiUrl . $endpoint . '?' . http_build_query($params);
            $response = $this->makeRequest($endpoint . '?' . http_build_query($params), 'GET');
            
            return [
                'success' => true,
                'estimated_amount' => $response['estimated_amount'] ?? $amount
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Make HTTP request to NOWPayments API
     */
    private function makeRequest($endpoint, $method = 'GET', $data = null) {
        $url = $this->apiUrl . $endpoint;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'x-api-key: ' . $this->apiKey,
            'Content-Type: application/json'
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("cURL error: " . $error);
        }
        
        $decoded = json_decode($response, true);
        
        if ($httpCode >= 400) {
            $errorMsg = $decoded['message'] ?? $decoded['error'] ?? 'Unknown error';
            throw new Exception("NOWPayments API error ({$httpCode}): {$errorMsg}");
        }
        
        return $decoded;
    }
    
    /**
     * Check if NOWPayments is configured
     */
    public function isConfigured() {
        return !empty($this->apiKey);
    }
}
