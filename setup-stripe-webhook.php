<?php
/**
 * Stripe Webhook Setup Script
 * This script will automatically create a webhook endpoint in your Stripe account
 */

require_once __DIR__ . '/php-backend/config.php';

echo "=== Stripe Webhook Setup ===\n\n";

// Check if Stripe library is available
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    echo "❌ Error: Stripe PHP library not found.\n";
    echo "Please run: composer require stripe/stripe-php\n";
    exit(1);
}

require_once __DIR__ . '/vendor/autoload.php';

\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

try {
    // Check if webhook already exists
    echo "Checking existing webhooks...\n";
    $webhooks = \Stripe\WebhookEndpoint::all(['limit' => 100]);
    
    $webhookUrl = BASE_URL . '/api/stripe/webhook';
    $existingWebhook = null;
    
    foreach ($webhooks->data as $webhook) {
        if ($webhook->url === $webhookUrl) {
            $existingWebhook = $webhook;
            break;
        }
    }
    
    if ($existingWebhook) {
        echo "✓ Webhook already exists!\n";
        echo "  URL: {$existingWebhook->url}\n";
        echo "  Status: {$existingWebhook->status}\n";
        echo "  Secret: {$existingWebhook->secret}\n\n";
        
        echo "Do you want to recreate it? (y/n): ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        fclose($handle);
        
        if (trim($line) !== 'y') {
            echo "\nKeeping existing webhook.\n";
            echo "\n✅ Your webhook is already configured!\n";
            exit(0);
        }
        
        // Delete old webhook
        echo "Deleting old webhook...\n";
        $existingWebhook->delete();
        echo "✓ Old webhook deleted\n\n";
    }
    
    // Create new webhook
    echo "Creating new webhook...\n";
    $webhook = \Stripe\WebhookEndpoint::create([
        'url' => $webhookUrl,
        'enabled_events' => [
            'checkout.session.completed',
            'checkout.session.expired',
            'payment_intent.succeeded',
            'payment_intent.payment_failed',
            'payment_intent.canceled',
        ],
        'description' => 'INTERNATIONAL PRO Payment Gateway',
    ]);
    
    echo "\n✅ SUCCESS! Webhook created!\n\n";
    echo "Webhook Details:\n";
    echo "  URL: {$webhook->url}\n";
    echo "  Status: {$webhook->status}\n";
    echo "  Webhook Secret: {$webhook->secret}\n\n";
    
    echo "⚠️  IMPORTANT: Update your .env file with this webhook secret:\n";
    echo "STRIPE_WEBHOOK_SECRET={$webhook->secret}\n\n";
    
    // Update .env file automatically
    $envFile = __DIR__ . '/.env';
    if (file_exists($envFile)) {
        $envContent = file_get_contents($envFile);
        
        // Replace existing webhook secret
        if (strpos($envContent, 'STRIPE_WEBHOOK_SECRET=') !== false) {
            $envContent = preg_replace(
                '/STRIPE_WEBHOOK_SECRET=.*/m',
                'STRIPE_WEBHOOK_SECRET=' . $webhook->secret,
                $envContent
            );
        } else {
            // Add webhook secret after Stripe keys
            $envContent = str_replace(
                "STRIPE_PUBLISHABLE_KEY=" . STRIPE_PUBLISHABLE_KEY,
                "STRIPE_PUBLISHABLE_KEY=" . STRIPE_PUBLISHABLE_KEY . "\n\n# Stripe Webhook Secret\nSTRIPE_WEBHOOK_SECRET=" . $webhook->secret,
                $envContent
            );
        }
        
        file_put_contents($envFile, $envContent);
        echo "✓ .env file updated automatically!\n\n";
    }
    
    echo "🎉 Webhook is now active and will automatically update payment statuses!\n\n";
    echo "Test your webhook at: https://dashboard.stripe.com/test/webhooks/{$webhook->id}\n";
    
} catch (\Stripe\Exception\ApiErrorException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
