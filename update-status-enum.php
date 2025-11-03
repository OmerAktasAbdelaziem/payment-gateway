<?php
/**
 * Update payment_links status enum to include processing and completed
 * Run once and then delete this file for security
 */

require_once __DIR__ . '/php-backend/config.php';

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    echo "Connected to database successfully.\n\n";
    
    // Update status enum to include processing and completed
    $sql = "ALTER TABLE payment_links MODIFY COLUMN status ENUM('active', 'processing', 'completed', 'inactive', 'expired') NOT NULL DEFAULT 'active'";
    
    if ($conn->query($sql) === TRUE) {
        echo "✓ Updated status enum to include 'processing' and 'completed'\n";
    } else {
        echo "Error: " . $conn->error . "\n";
    }
    
    echo "\n✅ SUCCESS! Database updated.\n";
    echo "⚠️  IMPORTANT: Please delete this file (update-status-enum.php) for security.\n\n";
    
    $conn->close();
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
?>
