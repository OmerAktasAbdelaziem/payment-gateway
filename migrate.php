<?php
/**
 * Database Migration Script
 * Creates all necessary tables for the payment gateway
 */

require_once __DIR__ . '/php-backend/config.php';
require_once __DIR__ . '/php-backend/Database.php';

echo "🚀 Starting database migration...\n\n";

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    echo "✓ Connected to database: " . DB_NAME . "\n\n";
    
    // Drop existing tables if they exist
    echo "📦 Dropping old tables if they exist...\n";
    $conn->exec("DROP TABLE IF EXISTS payments");
    $conn->exec("DROP TABLE IF EXISTS users");
    echo "✓ Old tables dropped\n\n";
    
    // Create users table
    echo "📦 Creating users table...\n";
    $conn->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL,
            INDEX idx_username (username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ Users table created\n\n";
    
    // Create payments table
    echo "📦 Creating payments table...\n";
    $conn->exec("
        CREATE TABLE IF NOT EXISTS payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            payment_id VARCHAR(100) UNIQUE NOT NULL,
            amount DECIMAL(10, 2) NOT NULL,
            currency VARCHAR(3) NOT NULL DEFAULT 'USD',
            description TEXT,
            customer_name VARCHAR(100),
            customer_email VARCHAR(100),
            customer_phone VARCHAR(20),
            status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
            stripe_payment_intent_id VARCHAR(100),
            payment_method VARCHAR(50),
            metadata JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            paid_at TIMESTAMP NULL,
            INDEX idx_payment_id (payment_id),
            INDEX idx_status (status),
            INDEX idx_created_at (created_at),
            INDEX idx_stripe_payment_intent_id (stripe_payment_intent_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ Payments table created\n\n";
    
    echo "✅ Database migration completed successfully!\n\n";
    
    // Show table structure
    echo "📋 Table Structure:\n";
    echo "==================\n\n";
    
    $tables = ['users', 'payments'];
    foreach ($tables as $table) {
        echo "Table: $table\n";
        $stmt = $conn->query("DESCRIBE $table");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $col) {
            echo "  - {$col['Field']} ({$col['Type']}) {$col['Key']}\n";
        }
        echo "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "🎉 Migration complete! Run seed.php to add sample data.\n";
