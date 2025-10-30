<?php
/**
 * CoinGate Service
 * Handles CoinGate payment order creation and status
 * API Docs: https://developer.coingate.com/docs/create-order
 */
class CoinGateService {
    private $apiToken;
    private $apiUrl = 'https://api.coingate.com/v2';

    public function __construct() {
        $this->apiToken = getenv('COINGATE_API_TOKEN');
    }

    /**
     * Create CoinGate order (invoice)
     */
    public function createOrder($paymentId, $amount, $currency = 'USD', $callbackUrl = '', $successUrl = '', $cancelUrl = '', $receiveCurrency = 'BTC') {
        $endpoint = $this->apiUrl . '/orders';
        $data = [
            'order_id' => $paymentId,
            'price_amount' => floatval($amount),
            'price_currency' => strtoupper($currency),
            'receive_currency' => strtoupper($receiveCurrency),
            'callback_url' => $callbackUrl,
            'cancel_url' => $cancelUrl,
            'success_url' => $successUrl,
        ];
        $headers = [
            'Authorization: Token ' . $this->apiToken,
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $result = json_decode($response, true);
        if ($httpCode === 201 && isset($result['payment_url'])) {
            return [
                'success' => true,
                'payment_url' => $result['payment_url'],
                'order_id' => $result['id'],
                'coingate_status' => $result['status'],
            ];
        } else {
            return [
                'success' => false,
                'error' => $result['message'] ?? 'CoinGate order creation failed',
                'response' => $result,
            ];
        }
    }
}
