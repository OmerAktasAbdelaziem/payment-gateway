<?php
/**
 * Stripe Service Class
 * Handles Stripe payment integration
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;

class StripeService {
    
    public function __construct() {
        // Set Stripe API key
        Stripe::setApiKey(STRIPE_SECRET_KEY);
    }
    
    /**
     * Create Stripe Checkout Session
     */
    public function createCheckoutSession($paymentId, $amount, $currency, $description) {
        try {
            // Convert amount to cents/smallest currency unit
            $amountInCents = intval($amount * 100);
            
            // Create Checkout Session
            $session = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => strtolower($currency),
                        'product_data' => [
                            'name' => $description ?: 'Payment',
                            'description' => 'Payment ID: ' . $paymentId,
                        ],
                        'unit_amount' => $amountInCents,
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => BASE_URL . '/payment-success.html?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => BASE_URL . '/pay/' . $paymentId . '?cancelled=1',
                'metadata' => [
                    'payment_link_id' => $paymentId
                ],
            ]);
            
            return [
                'success' => true,
                'sessionId' => $session->id,
                'url' => $session->url
            ];
        } catch (ApiErrorException $e) {
            error_log("Stripe Checkout Session creation failed: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Verify Stripe Webhook
     */
    public function verifyWebhook($payload, $signature) {
        try {
            return \Stripe\Webhook::constructEvent(
                $payload,
                $signature,
                STRIPE_WEBHOOK_SECRET
            );
        } catch (\Exception $e) {
            error_log("Webhook verification failed: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Create Stripe Payment Intent
     */
    public function createPaymentIntent($paymentId, $amount, $currency, $description) {
        try {
            // Convert amount to cents/smallest currency unit
            $amountInCents = intval($amount * 100);
            
            // Create PaymentIntent
            $paymentIntent = PaymentIntent::create([
                'amount' => $amountInCents,
                'currency' => strtolower($currency),
                'description' => $description,
                'metadata' => [
                    'payment_id' => $paymentId
                ],
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
            ]);
            
            return [
                'success' => true,
                'client_secret' => $paymentIntent->client_secret,
                'payment_intent_id' => $paymentIntent->id
            ];
        } catch (ApiErrorException $e) {
            error_log("Stripe PaymentIntent creation failed: " . $e->getMessage());
            throw new Exception("Failed to create payment intent: " . $e->getMessage());
        }
    }
    
    /**
     * Retrieve Payment Intent
     */
    public function getPaymentIntent($paymentIntentId) {
        try {
            $paymentIntent = PaymentIntent::retrieve($paymentIntentId);
            
            return [
                'success' => true,
                'payment_intent' => $paymentIntent
            ];
        } catch (ApiErrorException $e) {
            error_log("Stripe PaymentIntent retrieval failed: " . $e->getMessage());
            throw new Exception("Failed to retrieve payment intent: " . $e->getMessage());
        }
    }
    
    /**
     * Handle Stripe Webhook
     */
    public function handleWebhook() {
        $payload = file_get_contents('php://input');
        $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        
        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sigHeader,
                STRIPE_WEBHOOK_SECRET
            );
        } catch (\UnexpectedValueException $e) {
            // Invalid payload
            http_response_code(400);
            return ['error' => 'Invalid payload'];
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            // Invalid signature
            http_response_code(400);
            return ['error' => 'Invalid signature'];
        }
        
        // Handle the event
        switch ($event->type) {
            case 'payment_intent.succeeded':
                $this->handlePaymentSuccess($event->data->object);
                break;
                
            case 'payment_intent.payment_failed':
                $this->handlePaymentFailure($event->data->object);
                break;
                
            case 'payment_intent.canceled':
                $this->handlePaymentCanceled($event->data->object);
                break;
                
            default:
                // Unhandled event type
                error_log("Unhandled Stripe event: " . $event->type);
        }
        
        return ['success' => true];
    }
    
    /**
     * Handle successful payment
     */
    private function handlePaymentSuccess($paymentIntent) {
        $paymentId = $paymentIntent->metadata->payment_id ?? null;
        
        if (!$paymentId) {
            error_log("Payment ID not found in PaymentIntent metadata");
            return;
        }
        
        try {
            // Update payment status in database
            $this->db->execute(
                "UPDATE payments 
                SET status = 'completed', 
                    stripe_payment_intent_id = ?,
                    paid_at = NOW(),
                    updated_at = NOW()
                WHERE payment_id = ?",
                [$paymentIntent->id, $paymentId]
            );
            
            error_log("Payment completed: " . $paymentId);
            
            // Trigger automatic USDT conversion if enabled
            if (defined('AUTO_CONVERT_TO_USDT') && AUTO_CONVERT_TO_USDT === true) {
                $this->triggerUSDTConversion($paymentIntent);
            }
        } catch (Exception $e) {
            error_log("Failed to update payment status: " . $e->getMessage());
        }
    }
    
    /**
     * Trigger automatic USD to USDT conversion
     */
    private function triggerUSDTConversion($paymentIntent) {
        try {
            require_once __DIR__ . '/BinanceService.php';
            
            $paymentId = $paymentIntent->metadata->payment_id ?? null;
            $amountUSD = $paymentIntent->amount / 100; // Convert cents to dollars
            
            if (!$paymentId || $amountUSD <= 0) {
                error_log("Invalid payment data for USDT conversion");
                return;
            }
            
            error_log("Starting USDT conversion for payment {$paymentId}: \${$amountUSD}");
            
            // Record conversion attempt
            $conversionId = $this->db->insert(
                "INSERT INTO usdt_conversions (
                    payment_id, 
                    usd_amount, 
                    usdt_amount, 
                    usdt_price, 
                    wallet_address, 
                    network, 
                    status
                ) VALUES (?, ?, 0, 0, ?, ?, 'pending')",
                [$paymentId, $amountUSD, USDT_WALLET_ADDRESS, USDT_NETWORK]
            );
            
            // Execute conversion
            $binance = new BinanceService();
            $result = $binance->convertAndWithdraw($amountUSD, $paymentId);
            
            if ($result['success']) {
                // Update conversion record with success
                $this->db->execute(
                    "UPDATE usdt_conversions 
                    SET status = 'completed',
                        usdt_amount = ?,
                        usdt_price = ?,
                        binance_order_id = ?,
                        binance_withdraw_id = ?,
                        completed_at = NOW()
                    WHERE id = ?",
                    [
                        $result['usdt_amount'],
                        $result['usdt_price'],
                        $result['buy_order_id'],
                        $result['withdraw_id'],
                        $conversionId
                    ]
                );
                
                error_log("USDT conversion successful: {$result['usdt_amount']} USDT sent to " . USDT_WALLET_ADDRESS);
            } else {
                // Update conversion record with failure
                $this->db->execute(
                    "UPDATE usdt_conversions 
                    SET status = 'failed',
                        error_message = ?
                    WHERE id = ?",
                    [$result['error'], $conversionId]
                );
                
                error_log("USDT conversion failed: " . $result['error']);
            }
            
        } catch (Exception $e) {
            error_log("USDT conversion error: " . $e->getMessage());
        }
    }
    
    /**
     * Handle failed payment
     */
    private function handlePaymentFailure($paymentIntent) {
        $paymentId = $paymentIntent->metadata->payment_id ?? null;
        
        if (!$paymentId) {
            error_log("Payment ID not found in PaymentIntent metadata");
            return;
        }
        
        try {
            // Update payment status in database
            $this->db->execute(
                "UPDATE payments 
                SET status = 'failed', 
                    stripe_payment_intent_id = ?,
                    updated_at = NOW()
                WHERE payment_id = ?",
                [$paymentIntent->id, $paymentId]
            );
            
            error_log("Payment failed: " . $paymentId);
        } catch (Exception $e) {
            error_log("Failed to update payment status: " . $e->getMessage());
        }
    }
    
    /**
     * Handle canceled payment
     */
    private function handlePaymentCanceled($paymentIntent) {
        $paymentId = $paymentIntent->metadata->payment_id ?? null;
        
        if (!$paymentId) {
            error_log("Payment ID not found in PaymentIntent metadata");
            return;
        }
        
        try {
            // Update payment status in database
            $this->db->execute(
                "UPDATE payments 
                SET status = 'cancelled', 
                    stripe_payment_intent_id = ?,
                    updated_at = NOW()
                WHERE payment_id = ?",
                [$paymentIntent->id, $paymentId]
            );
            
            error_log("Payment cancelled: " . $paymentId);
        } catch (Exception $e) {
            error_log("Failed to update payment status: " . $e->getMessage());
        }
    }
    
    /**
     * Get Stripe public key
     */
    public function getPublicKey() {
        return STRIPE_PUBLIC_KEY;
    }
}
