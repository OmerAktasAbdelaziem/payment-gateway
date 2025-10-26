const sqlite3 = require('sqlite3').verbose();
const path = require('path');

// Database path from environment or default
const DB_PATH = process.env.DATABASE_PATH || path.join(__dirname, '../config/payments.db');

// Create and initialize database
class Database {
  constructor() {
    this.db = null;
  }

  // Initialize database connection
  init() {
    return new Promise((resolve, reject) => {
      this.db = new sqlite3.Database(DB_PATH, (err) => {
        if (err) {
          console.error('❌ Database connection error:', err.message);
          reject(err);
        } else {
          console.log('✅ Connected to SQLite database');
          this.createTables()
            .then(() => resolve())
            .catch(reject);
        }
      });
    });
  }

  // Create database tables
  createTables() {
    return new Promise((resolve, reject) => {
      const createPaymentsTable = `
        CREATE TABLE IF NOT EXISTS payments (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          payment_id TEXT UNIQUE NOT NULL,
          payment_link_id TEXT UNIQUE NOT NULL,
          stripe_payment_intent_id TEXT,
          amount REAL NOT NULL,
          currency TEXT DEFAULT 'USD',
          status TEXT DEFAULT 'pending',
          customer_email TEXT,
          customer_name TEXT,
          usdt_amount REAL,
          usdt_transaction_hash TEXT,
          exchange_order_id TEXT,
          stripe_fee REAL,
          exchange_fee REAL,
          network_fee REAL,
          total_fees REAL,
          metadata TEXT,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          completed_at DATETIME
        )
      `;

      const createLogsTable = `
        CREATE TABLE IF NOT EXISTS transaction_logs (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          payment_id TEXT NOT NULL,
          event_type TEXT NOT NULL,
          event_data TEXT,
          status TEXT,
          error_message TEXT,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (payment_id) REFERENCES payments(payment_id)
        )
      `;

      this.db.run(createPaymentsTable, (err) => {
        if (err) {
          console.error('❌ Error creating payments table:', err.message);
          reject(err);
        } else {
          console.log('✅ Payments table ready');
          
          this.db.run(createLogsTable, (err) => {
            if (err) {
              console.error('❌ Error creating logs table:', err.message);
              reject(err);
            } else {
              console.log('✅ Transaction logs table ready');
              resolve();
            }
          });
        }
      });
    });
  }

  // Create new payment record
  createPayment(data) {
    return new Promise((resolve, reject) => {
      const sql = `
        INSERT INTO payments (
          payment_id, payment_link_id, amount, currency, 
          customer_email, customer_name, metadata
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
      `;

      const params = [
        data.payment_id,
        data.payment_link_id,
        data.amount,
        data.currency || 'USD',
        data.customer_email || null,
        data.customer_name || null,
        data.metadata ? JSON.stringify(data.metadata) : null
      ];

      this.db.run(sql, params, function(err) {
        if (err) {
          reject(err);
        } else {
          resolve({ id: this.lastID, payment_id: data.payment_id });
        }
      });
    });
  }

  // Get payment by payment_link_id
  getPaymentByLinkId(payment_link_id) {
    return new Promise((resolve, reject) => {
      const sql = 'SELECT * FROM payments WHERE payment_link_id = ?';
      
      this.db.get(sql, [payment_link_id], (err, row) => {
        if (err) {
          reject(err);
        } else {
          resolve(row);
        }
      });
    });
  }

  // Get payment by payment_id
  getPaymentById(payment_id) {
    return new Promise((resolve, reject) => {
      const sql = 'SELECT * FROM payments WHERE payment_id = ?';
      
      this.db.get(sql, [payment_id], (err, row) => {
        if (err) {
          reject(err);
        } else {
          resolve(row);
        }
      });
    });
  }

  // Get payment by Stripe payment intent ID
  getPaymentByStripeId(stripe_payment_intent_id) {
    return new Promise((resolve, reject) => {
      const sql = 'SELECT * FROM payments WHERE stripe_payment_intent_id = ?';
      
      this.db.get(sql, [stripe_payment_intent_id], (err, row) => {
        if (err) {
          reject(err);
        } else {
          resolve(row);
        }
      });
    });
  }

  // Update payment
  updatePayment(payment_id, updates) {
    return new Promise((resolve, reject) => {
      const fields = [];
      const values = [];

      // Build dynamic UPDATE query
      Object.keys(updates).forEach(key => {
        fields.push(`${key} = ?`);
        values.push(updates[key]);
      });

      // Always update the updated_at timestamp
      fields.push('updated_at = CURRENT_TIMESTAMP');
      values.push(payment_id);

      const sql = `UPDATE payments SET ${fields.join(', ')} WHERE payment_id = ?`;

      this.db.run(sql, values, function(err) {
        if (err) {
          reject(err);
        } else {
          resolve({ changes: this.changes });
        }
      });
    });
  }

  // Get all payments with optional filters
  getAllPayments(filters = {}) {
    return new Promise((resolve, reject) => {
      let sql = 'SELECT * FROM payments';
      const conditions = [];
      const params = [];

      // Add filters
      if (filters.status) {
        conditions.push('status = ?');
        params.push(filters.status);
      }

      if (filters.limit) {
        sql += ' ORDER BY created_at DESC LIMIT ?';
        params.push(filters.limit);
      } else {
        sql += ' ORDER BY created_at DESC';
      }

      if (conditions.length > 0) {
        sql += ' WHERE ' + conditions.join(' AND ');
      }

      this.db.all(sql, params, (err, rows) => {
        if (err) {
          reject(err);
        } else {
          resolve(rows);
        }
      });
    });
  }

  // Log transaction event
  logEvent(payment_id, event_type, event_data, status = 'success', error_message = null) {
    return new Promise((resolve, reject) => {
      const sql = `
        INSERT INTO transaction_logs (
          payment_id, event_type, event_data, status, error_message
        ) VALUES (?, ?, ?, ?, ?)
      `;

      const params = [
        payment_id,
        event_type,
        event_data ? JSON.stringify(event_data) : null,
        status,
        error_message
      ];

      this.db.run(sql, params, function(err) {
        if (err) {
          reject(err);
        } else {
          resolve({ id: this.lastID });
        }
      });
    });
  }

  // Get logs for a payment
  getPaymentLogs(payment_id) {
    return new Promise((resolve, reject) => {
      const sql = 'SELECT * FROM transaction_logs WHERE payment_id = ? ORDER BY created_at ASC';
      
      this.db.all(sql, [payment_id], (err, rows) => {
        if (err) {
          reject(err);
        } else {
          resolve(rows);
        }
      });
    });
  }

  // Close database connection
  close() {
    return new Promise((resolve, reject) => {
      if (this.db) {
        this.db.close((err) => {
          if (err) {
            reject(err);
          } else {
            console.log('✅ Database connection closed');
            resolve();
          }
        });
      } else {
        resolve();
      }
    });
  }

  // Get database instance
  getDb() {
    return this.db;
  }
}

// Export singleton instance
const database = new Database();
module.exports = database;
module.exports.getDb = () => database.db;
