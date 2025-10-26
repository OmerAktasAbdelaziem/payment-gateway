const mysql = require('mysql2/promise');

// Database configuration from environment
const dbConfig = {
  host: process.env.DB_HOST || 'localhost',
  port: process.env.DB_PORT || 3306,
  user: process.env.DB_USER,
  password: process.env.DB_PASSWORD,
  database: process.env.DB_NAME,
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0
};

// Create and initialize database
class Database {
  constructor() {
    this.pool = null;
  }

  // Ensure pool is initialized
  async ensureConnection() {
    if (!this.pool) {
      console.log('⚠️  Database pool not initialized, reconnecting...');
      await this.init();
    }
  }

  // Initialize database connection
  async init() {
    try {
      this.pool = mysql.createPool(dbConfig);
      
      // Test connection
      const connection = await this.pool.getConnection();
      console.log('✅ Connected to MySQL database');
      connection.release();
      
      await this.createTables();
      return Promise.resolve();
    } catch (err) {
      console.error('❌ Database connection error:', err.message);
      return Promise.reject(err);
    }
  }

  // Create database tables
  async createTables() {
    const connection = await this.pool.getConnection();
    
    try {
      // Payments table
      await connection.query(`
        CREATE TABLE IF NOT EXISTS payments (
          id INT AUTO_INCREMENT PRIMARY KEY,
          payment_id VARCHAR(255) UNIQUE NOT NULL,
          payment_link_id VARCHAR(255) UNIQUE NOT NULL,
          stripe_payment_intent_id VARCHAR(255),
          amount DECIMAL(10, 2) NOT NULL,
          currency VARCHAR(10) DEFAULT 'USD',
          status VARCHAR(50) DEFAULT 'pending',
          customer_email VARCHAR(255),
          customer_name VARCHAR(255),
          usdt_amount DECIMAL(18, 8),
          usdt_transaction_hash VARCHAR(255),
          exchange_order_id VARCHAR(255),
          stripe_fee DECIMAL(10, 2),
          exchange_fee DECIMAL(10, 2),
          network_fee DECIMAL(10, 2),
          total_fees DECIMAL(10, 2),
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_payment_id (payment_id),
          INDEX idx_payment_link_id (payment_link_id),
          INDEX idx_stripe_payment_intent (stripe_payment_intent_id),
          INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
      `);
      console.log('✅ Payments table ready');

      // Transaction logs table
      await connection.query(`
        CREATE TABLE IF NOT EXISTS transaction_logs (
          id INT AUTO_INCREMENT PRIMARY KEY,
          payment_id VARCHAR(255) NOT NULL,
          event_type VARCHAR(100) NOT NULL,
          event_data TEXT,
          status VARCHAR(50) DEFAULT 'info',
          error_message TEXT,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_payment_id (payment_id),
          INDEX idx_event_type (event_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
      `);
      console.log('✅ Transaction logs table ready');

      // Users table
      await connection.query(`
        CREATE TABLE IF NOT EXISTS users (
          id INT AUTO_INCREMENT PRIMARY KEY,
          username VARCHAR(100) UNIQUE NOT NULL,
          password_hash VARCHAR(255) NOT NULL,
          email VARCHAR(255),
          role VARCHAR(50) DEFAULT 'admin',
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          last_login TIMESTAMP NULL,
          INDEX idx_username (username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
      `);
      console.log('✅ Users table ready');

    } finally {
      connection.release();
    }
  }

  // Create a new payment
  async createPayment(paymentData) {
    await this.ensureConnection();
    const [result] = await this.pool.execute(
      `INSERT INTO payments (
        payment_id, payment_link_id, amount, currency, 
        customer_email, customer_name, status
      ) VALUES (?, ?, ?, ?, ?, ?, ?)`,
      [
        paymentData.payment_id,
        paymentData.payment_link_id,
        paymentData.amount,
        paymentData.currency || 'USD',
        paymentData.customer_email || null,
        paymentData.customer_name || null,
        paymentData.status || 'pending'
      ]
    );
    return { id: result.insertId };
  }

  // Get payment by ID
  async getPaymentById(payment_id) {
    await this.ensureConnection();
    const [rows] = await this.pool.execute(
      'SELECT * FROM payments WHERE payment_id = ?',
      [payment_id]
    );
    return rows[0] || null;
  }

  // Get payment by payment link ID
  async getPaymentByLinkId(payment_link_id) {
    await this.ensureConnection();
    const [rows] = await this.pool.execute(
      'SELECT * FROM payments WHERE payment_link_id = ?',
      [payment_link_id]
    );
    return rows[0] || null;
  }

  // Get payment by Stripe payment intent ID
  async getPaymentByStripeId(stripe_payment_intent_id) {
    await this.ensureConnection();
    const [rows] = await this.pool.execute(
      'SELECT * FROM payments WHERE stripe_payment_intent_id = ?',
      [stripe_payment_intent_id]
    );
    return rows[0] || null;
  }

  // Update payment
  async updatePayment(payment_id, updateData) {
    await this.ensureConnection();
    const fields = [];
    const values = [];

    Object.keys(updateData).forEach(key => {
      if (updateData[key] !== undefined) {
        fields.push(`${key} = ?`);
        values.push(updateData[key]);
      }
    });

    if (fields.length === 0) return { changes: 0 };

    values.push(payment_id);
    const [result] = await this.pool.execute(
      `UPDATE payments SET ${fields.join(', ')} WHERE payment_id = ?`,
      values
    );
    return { changes: result.affectedRows };
  }

  // Get all payments
  async getAllPayments(limit = 100) {
    await this.ensureConnection();
    
    const [rows] = await this.pool.execute(
      'SELECT * FROM payments ORDER BY created_at DESC LIMIT ?',
      [limit]
    );
    return rows;
  }

  // Log transaction event
  async logTransaction(payment_id, event_type, event_data = null, status = 'info', error_message = null) {
    await this.ensureConnection();
    const [result] = await this.pool.execute(
      `INSERT INTO transaction_logs 
       (payment_id, event_type, event_data, status, error_message) 
       VALUES (?, ?, ?, ?, ?)`,
      [
        payment_id,
        event_type,
        event_data ? JSON.stringify(event_data) : null,
        status,
        error_message
      ]
    );
    return { id: result.insertId };
  }

  // Get logs for a payment
  async getPaymentLogs(payment_id) {
    const [rows] = await this.pool.execute(
      'SELECT * FROM transaction_logs WHERE payment_id = ? ORDER BY created_at ASC',
      [payment_id]
    );
    return rows;
  }

  // Get database instance (for auth service)
  getDb() {
    return this.pool;
  }

  // Close database connection
  async close() {
    if (this.pool) {
      await this.pool.end();
      console.log('✅ Database connection closed');
    }
  }
}

// Export singleton instance
const database = new Database();
module.exports = database;
module.exports.getDb = () => database.pool;
