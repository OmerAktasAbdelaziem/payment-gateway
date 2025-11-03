<?php
/**
 * Clear Payment Links History
 * This script will delete all payment links and associated payments from the database
 * Run once and then delete this file for security
 */

require_once __DIR__ . '/php-backend/config.php';

try {
    // Connect to database
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    echo "Connected to database successfully.\n\n";
    
    // Start transaction
    $conn->begin_transaction();
    
    // Get counts before deletion
    $result = $conn->query("SELECT COUNT(*) as count FROM payment_links");
    $paymentLinksCount = $result->fetch_assoc()['count'];
    
    $result = $conn->query("SELECT COUNT(*) as count FROM payments");
    $paymentsCount = $result->fetch_assoc()['count'];
    
    echo "Current records:\n";
    echo "- Payment Links: $paymentLinksCount\n";
    echo "- Payments: $paymentsCount\n\n";
    
    // Delete all payments first (due to foreign key)
    $conn->query("DELETE FROM payments");
    echo "✓ Deleted all payments\n";
    
    // Delete all payment links
    $conn->query("DELETE FROM payment_links");
    echo "✓ Deleted all payment links\n";
    
    // Reset auto-increment for payments table
    $conn->query("ALTER TABLE payments AUTO_INCREMENT = 1");
    echo "✓ Reset payments table auto-increment\n";
    
    // Commit transaction
    $conn->commit();
    
    echo "\n✅ SUCCESS! All payment history has been cleared.\n";
    echo "⚠️  IMPORTANT: Please delete this file (clear-history.php) for security.\n\n";
    
    $conn->close();
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
?>
