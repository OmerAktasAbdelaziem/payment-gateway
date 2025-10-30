<?php
/**
 * Payment Class
 * Handles payment creation and management
 */

class Payment {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Create new payment
     */
    public function create($data) {
        $requiredFields = ['amount', 'currency', 'description'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }
        
        // Validate amount
        $amount = floatval($data['amount']);
        if ($amount <= 0) {
            throw new Exception("Amount must be greater than 0");
        }
        
        // Generate unique payment ID
        $paymentId = $this->generatePaymentId();
        
        // Insert payment into database
        $id = $this->db->insert(
            "INSERT INTO payments (
                payment_id, 
                amount, 
                currency, 
                description, 
                customer_name,
                customer_email,
                customer_phone,
                status,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())",
            [
                $paymentId,
                $amount,
                strtoupper($data['currency']),
                $data['description'],
                $data['customer_name'] ?? null,
                $data['customer_email'] ?? null,
                $data['customer_phone'] ?? null
            ]
        );
        
        return [
            'success' => true,
            'payment' => $this->getById($id)
        ];
    }
    
    /**
     * Get payment by ID (database ID)
     */
    public function getById($id) {
        $payment = $this->db->fetchOne(
            "SELECT * FROM payments WHERE id = ?",
            [$id]
        );
        
        if (!$payment) {
            throw new Exception("Payment not found");
        }
        
        return $this->formatPayment($payment);
    }
    
    /**
     * Get payment by payment_id (public ID)
     */
    public function getByPaymentId($paymentId) {
        $payment = $this->db->fetchOne(
            "SELECT * FROM payments WHERE payment_id = ?",
            [$paymentId]
        );
        
        if (!$payment) {
            throw new Exception("Payment not found");
        }
        
        return $this->formatPayment($payment);
    }
    
    /**
     * Get all payments
     */
    public function getAll($filters = []) {
        $sql = "SELECT * FROM payments WHERE 1=1";
        $params = [];
        
        // Add filters
        if (!empty($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (payment_id LIKE ? OR description LIKE ? OR customer_email LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        // Add sorting
        $sql .= " ORDER BY created_at DESC";
        
        // Add limit
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT ?";
            $params[] = intval($filters['limit']);
        }
        
        $payments = $this->db->fetchAll($sql, $params);
        
        return array_map([$this, 'formatPayment'], $payments);
    }
    
    /**
     * Update payment status
     */
    public function updateStatus($paymentId, $status, $stripePaymentIntentId = null) {
        $validStatuses = ['pending', 'processing', 'completed', 'failed', 'cancelled'];
        if (!in_array($status, $validStatuses)) {
            throw new Exception("Invalid status");
        }
        
        $sql = "UPDATE payments SET status = ?, updated_at = NOW()";
        $params = [$status];
        
        if ($stripePaymentIntentId) {
            $sql .= ", stripe_payment_intent_id = ?";
            $params[] = $stripePaymentIntentId;
        }
        
        if ($status === 'completed') {
            $sql .= ", paid_at = NOW()";
        }
        
        $sql .= " WHERE payment_id = ?";
        $params[] = $paymentId;
        
        $affected = $this->db->execute($sql, $params);
        
        if ($affected === 0) {
            throw new Exception("Payment not found or not updated");
        }
        
        return $this->getByPaymentId($paymentId);
    }
    
    /**
     * Delete payment
     */
    public function delete($paymentId) {
        $affected = $this->db->execute(
            "DELETE FROM payments WHERE payment_id = ?",
            [$paymentId]
        );
        
        if ($affected === 0) {
            throw new Exception("Payment not found");
        }
        
        return [
            'success' => true,
            'message' => 'Payment deleted successfully'
        ];
    }
    
    /**
     * Get payment statistics
     */
    public function getStats() {
        $stats = $this->db->fetchOne("
            SELECT 
                COUNT(*) as total_payments,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_payments,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_payments,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_payments,
                SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_revenue,
                AVG(CASE WHEN status = 'completed' THEN amount ELSE NULL END) as average_payment
            FROM payments
        ");
        
        return [
            'total_payments' => intval($stats['total_payments']),
            'completed_payments' => intval($stats['completed_payments']),
            'pending_payments' => intval($stats['pending_payments']),
            'failed_payments' => intval($stats['failed_payments']),
            'total_revenue' => floatval($stats['total_revenue']),
            'average_payment' => floatval($stats['average_payment'])
        ];
    }
    
    /**
     * Generate unique payment ID
     */
    private function generatePaymentId() {
        return 'PAY_' . strtoupper(bin2hex(random_bytes(16)));
    }
    
    /**
     * Format payment data
     */
    private function formatPayment($payment) {
        return [
            'id' => intval($payment['id']),
            'payment_id' => $payment['payment_id'],
            'amount' => floatval($payment['amount']),
            'currency' => $payment['currency'],
            'description' => $payment['description'],
            'customer_name' => $payment['customer_name'],
            'customer_email' => $payment['customer_email'],
            'customer_phone' => $payment['customer_phone'],
            'status' => $payment['status'],
            'stripe_payment_intent_id' => $payment['stripe_payment_intent_id'],
            'payment_url' => BASE_URL . '/pay/' . $payment['payment_id'],
            'created_at' => $payment['created_at'],
            'updated_at' => $payment['updated_at'],
            'paid_at' => $payment['paid_at']
        ];
    }
}
