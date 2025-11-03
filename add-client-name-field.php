<?php
/**
 * Add client_name field to payment_links table
 * Run once and then delete this file for security
 */

require_once __DIR__ . '/php-backend/config.php';

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    echo "Connected to database successfully.\n\n";
    
    // Add client_name column to payment_links table
    $sql = "ALTER TABLE payment_links ADD COLUMN client_name VARCHAR(255) NOT NULL AFTER description";
    
    if ($conn->query($sql) === TRUE) {
        echo "✓ Added client_name column to payment_links table\n";
    } else {
        echo "Error: " . $conn->error . "\n";
    }
    
    echo "\n✅ SUCCESS! Database updated.\n";
    echo "⚠️  IMPORTANT: Please delete this file (add-client-name-field.php) for security.\n\n";
    
    $conn->close();
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
?>
