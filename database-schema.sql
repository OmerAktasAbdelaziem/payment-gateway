-- Payment Gateway Database Schema
-- Run this on your MySQL database: u402548537_gateway

-- Create payment_links table
CREATE TABLE IF NOT EXISTS `payment_links` (
  `id` VARCHAR(100) PRIMARY KEY,
  `amount` DECIMAL(10, 2) NOT NULL,
  `currency` VARCHAR(10) NOT NULL DEFAULT 'USD',
  `description` TEXT,
  `status` ENUM('active', 'inactive', 'expired') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create payments table (tracks actual payments)
CREATE TABLE IF NOT EXISTS `payments` (
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
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`payment_link_id`) REFERENCES `payment_links`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create indexes for better performance
CREATE INDEX idx_payment_link_status ON payment_links(status);
CREATE INDEX idx_payment_link_created ON payment_links(created_at);
CREATE INDEX idx_payment_status ON payments(status);
CREATE INDEX idx_payment_external_id ON payments(external_id);
CREATE INDEX idx_payment_created ON payments(created_at);

-- Insert a sample payment link for testing
INSERT INTO `payment_links` (`id`, `amount`, `currency`, `description`, `status`) 
VALUES ('PAY_SAMPLE123', 100.00, 'USD', 'Sample Payment Link', 'active');
