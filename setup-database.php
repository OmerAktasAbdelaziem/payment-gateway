<?php
/**
 * Database Setup Script
 * Run this once to create all necessary tables
 */

require_once __DIR__ . '/php-backend/config.php';

echo "<!DOCTYPE html><html><head><title>Database Setup</title>";
echo "<style>body{font-family:monospace;padding:40px;background:#1f2937;color:#10b981;}";
echo ".success{color:#10b981;}.error{color:#ef4444;}.info{color:#3b82f6;}</style></head><body>";

echo "<h1>🚀 Payment Gateway Database Setup</h1><hr><br>";

try {
    // Connect to database
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    echo "<div class='success'>✅ Connected to database: " . DB_NAME . "</div><br>";
    
    // Create payment_links table
    echo "<div class='info'>Creating payment_links table...</div>";
    $sql = "CREATE TABLE IF NOT EXISTS `payment_links` (
      `id` VARCHAR(100) PRIMARY KEY,
      `amount` DECIMAL(10, 2) NOT NULL,
      `currency` VARCHAR(10) NOT NULL DEFAULT 'USD',
      `description` TEXT,
      `status` ENUM('active', 'inactive', 'expired') DEFAULT 'active',
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($sql) === TRUE) {
        echo "<div class='success'>✅ payment_links table created successfully</div><br>";
    } else {
        throw new Exception("Error creating payment_links table: " . $conn->error);
    }
    
    // Create payments table
    echo "<div class='info'>Creating payments table...</div>";
    $sql = "CREATE TABLE IF NOT EXISTS `payments` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `payment_link_id` VARCHAR(100) NOT NULL,
      `external_id` VARCHAR(255),
      `amount` DECIMAL(10, 2) NOT NULL,
      `currency` VARCHAR(10) NOT NULL,
      `status` ENUM('pending', 'processing', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
      `provider` VARCHAR(50) NOT NULL COMMENT 'stripe, finchpay, coingate, etc',
      `customer_email` VARCHAR(255),
      `customer_name` VARCHAR(255),
      `metadata` TEXT,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($sql) === TRUE) {
        echo "<div class='success'>✅ payments table created successfully</div><br>";
    } else {
        throw new Exception("Error creating payments table: " . $conn->error);
    }
    
    // Create indexes (with error handling)
    echo "<div class='info'>Creating indexes...</div>";
    
    $indexes = [
        "CREATE INDEX idx_payment_link_status ON payment_links(status)",
        "CREATE INDEX idx_payment_link_created ON payment_links(created_at)",
        "CREATE INDEX idx_payment_status ON payments(status)",
        "CREATE INDEX idx_payment_external_id ON payments(external_id)",
        "CREATE INDEX idx_payment_created ON payments(created_at)"
    ];
    
    foreach ($indexes as $index) {
        try {
            if ($conn->query($index) === TRUE) {
                echo "<div class='success'>✅ Index created</div>";
            }
        } catch (Exception $e) {
            // Index might already exist, ignore error
            echo "<div class='info'>⚠️ Index already exists (skipped)</div>";
        }
    }
    
    echo "<br>";
    
    // Insert sample data
    echo "<div class='info'>Inserting sample payment link...</div>";
    $sampleId = 'PAY_' . strtoupper(bin2hex(random_bytes(20)));
    $sql = "INSERT INTO payment_links (id, amount, currency, description, status) 
            VALUES (?, 100.00, 'USD', 'Sample Payment Link - Test', 'active')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $sampleId);
    
    if ($stmt->execute()) {
        echo "<div class='success'>✅ Sample payment link created: {$sampleId}</div>";
        echo "<div class='info'>Test link: <a href='" . BASE_URL . "/pay/{$sampleId}' target='_blank' style='color:#3b82f6;'>" . BASE_URL . "/pay/{$sampleId}</a></div>";
    }
    $stmt->close();
    
    echo "<br><hr><br>";
    echo "<div class='success'><h2>✅ Database setup completed successfully!</h2></div>";
    echo "<div class='info'>You can now access the admin dashboard: <a href='/admin-dashboard.php' style='color:#3b82f6;'>Admin Dashboard</a></div>";
    
    // Show table info
    echo "<br><br><h3>📊 Database Tables:</h3>";
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_array()) {
        echo "<div class='success'>✅ " . $row[0] . "</div>";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Error: " . $e->getMessage() . "</div>";
}

echo "<br><br><div class='info'>⚠️ For security, delete this file after setup: setup-database.php</div>";
echo "</body></html>";
?>
