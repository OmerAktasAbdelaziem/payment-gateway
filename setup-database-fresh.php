<?php
/**
 * Database Setup Script - Simple Version
 * This will DROP existing tables and create fresh ones
 */

require_once __DIR__ . '/php-backend/config.php';

echo "<!DOCTYPE html><html><head><title>Database Setup</title>";
echo "<style>body{font-family:monospace;padding:40px;background:#1f2937;color:#10b981;}";
echo ".success{color:#10b981;}.error{color:#ef4444;}.info{color:#3b82f6;}.warning{color:#f59e0b;}</style></head><body>";

echo "<h1>🚀 Payment Gateway Database Setup</h1><hr><br>";

try {
    // Connect to database
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    echo "<div class='success'>✅ Connected to database: " . DB_NAME . "</div><br>";
    
    // Drop existing tables (if any)
    echo "<div class='warning'>⚠️ Dropping existing tables (if any)...</div>";
    $conn->query("DROP TABLE IF EXISTS payments");
    $conn->query("DROP TABLE IF EXISTS payment_links");
    echo "<div class='success'>✅ Old tables removed</div><br>";
    
    // Create payment_links table
    echo "<div class='info'>Creating payment_links table...</div>";
    $sql = "CREATE TABLE `payment_links` (
      `id` VARCHAR(100) PRIMARY KEY,
      `amount` DECIMAL(10, 2) NOT NULL,
      `currency` VARCHAR(10) NOT NULL DEFAULT 'USD',
      `description` TEXT,
      `status` ENUM('active', 'inactive', 'expired') DEFAULT 'active',
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX idx_status (status),
      INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($sql) === TRUE) {
        echo "<div class='success'>✅ payment_links table created</div><br>";
    } else {
        throw new Exception("Error creating payment_links: " . $conn->error);
    }
    
    // Create payments table
    echo "<div class='info'>Creating payments table...</div>";
    $sql = "CREATE TABLE `payments` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `payment_link_id` VARCHAR(100) NOT NULL,
      `external_id` VARCHAR(255),
      `amount` DECIMAL(10, 2) NOT NULL,
      `currency` VARCHAR(10) NOT NULL,
      `status` ENUM('pending', 'processing', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
      `provider` VARCHAR(50) NOT NULL,
      `customer_email` VARCHAR(255),
      `customer_name` VARCHAR(255),
      `metadata` TEXT,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX idx_payment_link (payment_link_id),
      INDEX idx_status (status),
      INDEX idx_external (external_id),
      INDEX idx_created (created_at),
      FOREIGN KEY (payment_link_id) REFERENCES payment_links(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($sql) === TRUE) {
        echo "<div class='success'>✅ payments table created</div><br>";
    } else {
        throw new Exception("Error creating payments: " . $conn->error);
    }
    
    // Insert sample data
    echo "<div class='info'>Creating sample payment link...</div>";
    $sampleId = 'PAY_' . strtoupper(bin2hex(random_bytes(20)));
    $sql = "INSERT INTO payment_links (id, amount, currency, description, status) 
            VALUES (?, 100.00, 'USD', 'Sample Payment - $100', 'active')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $sampleId);
    
    if ($stmt->execute()) {
        echo "<div class='success'>✅ Sample link created: {$sampleId}</div>";
        echo "<div class='info'>🔗 Test URL: <a href='" . BASE_URL . "/pay/{$sampleId}' target='_blank' style='color:#3b82f6;'>" . BASE_URL . "/pay/{$sampleId}</a></div>";
    }
    $stmt->close();
    
    echo "<br><hr><br>";
    echo "<div class='success'><h2>✅ SETUP COMPLETED!</h2></div>";
    echo "<div class='info'>📊 Database: " . DB_NAME . "</div>";
    echo "<div class='info'>📋 Tables: payment_links, payments</div>";
    echo "<div class='info'>🔗 Admin: <a href='/admin-dashboard.php' style='color:#3b82f6;'>https://internationalitpro.com/admin-dashboard.php</a></div>";
    echo "<div class='info'>👤 Login: admin / admin123</div>";
    
    echo "<br><br><div class='warning'>⚠️ SECURITY: Delete this file now!</div>";
    echo "<div class='info'>Run: <code>rm setup-database-fresh.php</code></div>";
    
    $conn->close();
    
} catch (Exception $e) {
    echo "<div class='error'>❌ ERROR: " . $e->getMessage() . "</div>";
    echo "<div class='info'>💡 Check your database credentials in .env file</div>";
}

echo "</body></html>";
?>
