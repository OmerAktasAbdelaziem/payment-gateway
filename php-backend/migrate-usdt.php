<?php
/**
 * Database Migration: Add USDT Conversion Tracking Table
 * Run this file once to create the usdt_conversions table
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Database.php';

try {
    $db = Database::getInstance();
    
    echo "🔄 Creating USDT conversions table...\n\n";
    
    // Drop table if exists
    $db->execute("DROP TABLE IF EXISTS usdt_conversions");
    
    // Create usdt_conversions table
    $db->execute("
        CREATE TABLE usdt_conversions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            payment_id VARCHAR(50) NOT NULL,
            usd_amount DECIMAL(10, 2) NOT NULL,
            usdt_amount DECIMAL(10, 6) NOT NULL,
            usdt_price DECIMAL(10, 6) NOT NULL,
            binance_order_id VARCHAR(100),
            binance_withdraw_id VARCHAR(100),
            wallet_address VARCHAR(100) NOT NULL,
            network VARCHAR(20) NOT NULL,
            status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
            error_message TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            completed_at TIMESTAMP NULL,
            INDEX idx_payment_id (payment_id),
            INDEX idx_status (status),
            INDEX idx_created_at (created_at),
            FOREIGN KEY (payment_id) REFERENCES payments(payment_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    echo "✅ Table 'usdt_conversions' created successfully!\n\n";
    
    // Show table structure
    $columns = $db->fetchAll("DESCRIBE usdt_conversions");
    
    echo "📋 Table Structure:\n";
    echo "-------------------\n";
    foreach ($columns as $column) {
        echo "• {$column['Field']} ({$column['Type']}) {$column['Key']}\n";
    }
    
    echo "\n✅ Database migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
