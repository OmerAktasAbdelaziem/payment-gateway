<?php
/**
 * Database Seed Script
 * Seeds the database with initial data
 */

require_once __DIR__ . '/php-backend/config.php';
require_once __DIR__ . '/php-backend/Database.php';
require_once __DIR__ . '/php-backend/Auth.php';

echo "🌱 Starting database seeding...\n\n";

try {
    $db = Database::getInstance();
    $auth = new Auth();
    
    echo "✓ Connected to database: " . DB_NAME . "\n\n";
    
    // Create admin user
    echo "👤 Creating admin user...\n";
    $result = $auth->createUser('gateway', 'Gateway2024$');
    
    if ($result['success']) {
        echo "✓ Admin user created\n";
        echo "  Username: gateway\n";
        echo "  Password: Gateway2024$\n\n";
    } else {
        echo "⚠️  Admin user already exists or error: " . $result['message'] . "\n\n";
    }
    
    // Create sample payments
    echo "💳 Creating sample payments...\n";
    
    $samplePayments = [
        [
            'payment_id' => 'PAY_SAMPLE001',
            'amount' => 99.99,
            'currency' => 'USD',
            'description' => 'Sample Product Purchase',
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'customer_phone' => '+1234567890',
            'status' => 'completed',
            'paid_at' => date('Y-m-d H:i:s', strtotime('-2 days'))
        ],
        [
            'payment_id' => 'PAY_SAMPLE002',
            'amount' => 149.50,
            'currency' => 'USD',
            'description' => 'Premium Service Subscription',
            'customer_name' => 'Jane Smith',
            'customer_email' => 'jane@example.com',
            'customer_phone' => '+1234567891',
            'status' => 'completed',
            'paid_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
        ],
        [
            'payment_id' => 'PAY_SAMPLE003',
            'amount' => 299.00,
            'currency' => 'USD',
            'description' => 'Enterprise Package',
            'customer_name' => 'Bob Johnson',
            'customer_email' => 'bob@example.com',
            'customer_phone' => '+1234567892',
            'status' => 'pending',
            'paid_at' => null
        ],
        [
            'payment_id' => 'PAY_SAMPLE004',
            'amount' => 49.99,
            'currency' => 'USD',
            'description' => 'Basic Plan',
            'customer_name' => 'Alice Williams',
            'customer_email' => 'alice@example.com',
            'customer_phone' => '+1234567893',
            'status' => 'failed',
            'paid_at' => null
        ],
        [
            'payment_id' => 'PAY_SAMPLE005',
            'amount' => 199.99,
            'currency' => 'USD',
            'description' => 'Professional Package',
            'customer_name' => 'Charlie Brown',
            'customer_email' => 'charlie@example.com',
            'customer_phone' => '+1234567894',
            'status' => 'processing',
            'paid_at' => null
        ]
    ];
    
    foreach ($samplePayments as $payment) {
        $sql = "INSERT INTO payments (
            payment_id, amount, currency, description, 
            customer_name, customer_email, customer_phone, 
            status, paid_at, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $db->query($sql, [
            $payment['payment_id'],
            $payment['amount'],
            $payment['currency'],
            $payment['description'],
            $payment['customer_name'],
            $payment['customer_email'],
            $payment['customer_phone'],
            $payment['status'],
            $payment['paid_at']
        ]);
        
        echo "  ✓ Created payment: {$payment['payment_id']} - {$payment['description']} ({$payment['status']})\n";
    }
    
    echo "\n✅ Database seeding completed successfully!\n\n";
    
    // Show summary
    echo "📊 Summary:\n";
    echo "===========\n";
    $userCount = $db->fetchOne("SELECT COUNT(*) as count FROM users")['count'];
    $paymentCount = $db->fetchOne("SELECT COUNT(*) as count FROM payments")['count'];
    $completedPayments = $db->fetchOne("SELECT COUNT(*) as count FROM payments WHERE status = 'completed'")['count'];
    $totalRevenue = $db->fetchOne("SELECT SUM(amount) as total FROM payments WHERE status = 'completed'")['total'];
    
    echo "  👥 Users: $userCount\n";
    echo "  💳 Payments: $paymentCount\n";
    echo "  ✅ Completed: $completedPayments\n";
    echo "  💰 Total Revenue: $" . number_format($totalRevenue, 2) . "\n\n";
    
    echo "🎉 Seeding complete!\n\n";
    echo "🔐 Login Credentials:\n";
    echo "   URL: " . BASE_URL . "/login\n";
    echo "   Username: gateway\n";
    echo "   Password: Gateway2024$\n\n";
    
} catch (Exception $e) {
    echo "❌ Seeding failed: " . $e->getMessage() . "\n";
    exit(1);
}
