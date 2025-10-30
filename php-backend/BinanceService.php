<?php
/**
 * Binance Service
 * Handles automatic USD to USDT conversion and withdrawal
 */

class BinanceService {
    private $apiKey;
    private $apiSecret;
    private $baseUrl = 'https://api.binance.com';
    
    public function __construct() {
        $this->apiKey = EXCHANGE_API_KEY;
        $this->apiSecret = EXCHANGE_API_SECRET;
    }
    
    /**
     * Convert USD to USDT and withdraw to wallet
     * This is called when a Stripe payment succeeds
     */
    public function convertAndWithdraw($usdAmount, $paymentId) {
        try {
            // Step 1: Get current USDT price
            $usdtPrice = $this->getUSDTPrice();
            
            // Step 2: Calculate USDT amount (1 USDT ≈ $1, but we check actual price)
            $usdtAmount = $usdAmount / $usdtPrice;
            
            // Step 3: Buy USDT on Binance
            $buyResult = $this->buyUSDT($usdtAmount);
            
            if (!$buyResult['success']) {
                throw new Exception("Failed to buy USDT: " . $buyResult['error']);
            }
            
            // Step 4: Wait a moment for order to settle
            sleep(2);
            
            // Step 5: Withdraw USDT to TRC20 wallet
            $withdrawResult = $this->withdrawUSDT($usdtAmount);
            
            if (!$withdrawResult['success']) {
                throw new Exception("Failed to withdraw USDT: " . $withdrawResult['error']);
            }
            
            return [
                'success' => true,
                'usd_amount' => $usdAmount,
                'usdt_amount' => $usdtAmount,
                'usdt_price' => $usdtPrice,
                'buy_order_id' => $buyResult['order_id'],
                'withdraw_id' => $withdrawResult['withdraw_id'],
                'wallet_address' => USDT_WALLET_ADDRESS,
                'network' => USDT_NETWORK,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            error_log("Binance conversion error for payment {$paymentId}: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get current USDT price in USD
     */
    private function getUSDTPrice() {
        try {
            $endpoint = '/api/v3/ticker/price';
            $url = $this->baseUrl . $endpoint . '?symbol=USDTUSDC';
            
            $response = $this->makeRequest($url, 'GET');
            
            // USDT is stable coin, typically $1.00
            // But we check actual market price
            if (isset($response['price'])) {
                return floatval($response['price']);
            }
            
            // Fallback to $1.00 if API fails
            return 1.00;
            
        } catch (Exception $e) {
            error_log("Failed to get USDT price: " . $e->getMessage());
            return 1.00; // Default to $1.00
        }
    }
    
    /**
     * Buy USDT using BUSD or USDC (stablecoins)
     * Note: You need to have USDC or BUSD in your Binance account first
     */
    private function buyUSDT($amount) {
        try {
            // First, check if we have sufficient balance
            $balance = $this->getBalance('USDC');
            
            if ($balance < $amount) {
                throw new Exception("Insufficient USDC balance. Have: {$balance}, Need: {$amount}");
            }
            
            $endpoint = '/api/v3/order';
            $params = [
                'symbol' => 'USDTUSDC',
                'side' => 'BUY',
                'type' => 'MARKET',
                'quantity' => number_format($amount, 2, '.', ''),
                'timestamp' => round(microtime(true) * 1000)
            ];
            
            $url = $this->baseUrl . $endpoint;
            $response = $this->makeRequest($url, 'POST', $params, true);
            
            return [
                'success' => true,
                'order_id' => $response['orderId'] ?? 'unknown',
                'executed_qty' => $response['executedQty'] ?? $amount
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Withdraw USDT to external wallet
     */
    private function withdrawUSDT($amount) {
        try {
            $endpoint = '/sapi/v1/capital/withdraw/apply';
            
            $params = [
                'coin' => 'USDT',
                'network' => USDT_NETWORK,
                'address' => USDT_WALLET_ADDRESS,
                'amount' => number_format($amount, 2, '.', ''),
                'timestamp' => round(microtime(true) * 1000)
            ];
            
            $url = $this->baseUrl . $endpoint;
            $response = $this->makeRequest($url, 'POST', $params, true);
            
            return [
                'success' => true,
                'withdraw_id' => $response['id'] ?? 'pending'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get account balance for a specific coin
     */
    private function getBalance($coin) {
        try {
            $endpoint = '/api/v3/account';
            $params = [
                'timestamp' => round(microtime(true) * 1000)
            ];
            
            $url = $this->baseUrl . $endpoint;
            $response = $this->makeRequest($url, 'GET', $params, true);
            
            if (isset($response['balances'])) {
                foreach ($response['balances'] as $balance) {
                    if ($balance['asset'] === $coin) {
                        return floatval($balance['free']);
                    }
                }
            }
            
            return 0;
            
        } catch (Exception $e) {
            error_log("Failed to get {$coin} balance: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Make HTTP request to Binance API
     */
    private function makeRequest($url, $method = 'GET', $params = [], $signed = false) {
        if ($signed) {
            // Add signature for authenticated requests
            $queryString = http_build_query($params);
            $signature = hash_hmac('sha256', $queryString, $this->apiSecret);
            $params['signature'] = $signature;
        }
        
        if ($method === 'GET' && !empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-MBX-APIKEY: ' . $this->apiKey,
            'Content-Type: application/json'
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($params)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
            }
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            $error = json_decode($response, true);
            throw new Exception($error['msg'] ?? 'Binance API error');
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Check if auto-conversion is enabled
     */
    public function isEnabled() {
        return defined('AUTO_CONVERT_TO_USDT') && AUTO_CONVERT_TO_USDT === true;
    }
    
    /**
     * Get conversion status for a payment
     */
    public function getConversionStatus($paymentId) {
        $db = Database::getInstance();
        
        $conversion = $db->fetchOne(
            "SELECT * FROM usdt_conversions WHERE payment_id = ?",
            [$paymentId]
        );
        
        return $conversion ?: null;
    }
}
