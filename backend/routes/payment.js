const express = require('express');
const router = express.Router();
const { v4: uuidv4 } = require('uuid');
const database = require('../services/database');
const stripeService = require('../services/stripe');
const usdtConversionService = require('../services/usdtConversion');

// Generate payment link
router.post('/create-payment-link', async (req, res) => {
  try {
    const { amount, currency, customer_email, customer_name, metadata } = req.body;

    // Validate amount
    if (!amount || amount <= 0) {
      return res.status(400).json({
        success: false,
        error: 'Invalid amount. Amount must be greater than 0.'
      });
    }

    // Generate unique IDs
    const payment_id = uuidv4();
    const payment_link_id = uuidv4();

    // Create payment record in database
    await database.createPayment({
      payment_id,
      payment_link_id,
      amount,
      currency: currency || 'USD',
      customer_email,
      customer_name,
      metadata
    });

    // Log event
    await database.logEvent(
      payment_id,
      'payment_link_created',
      { amount, currency, payment_link_id }
    );

    // Generate payment URL
    const baseUrl = process.env.BASE_URL || `http://localhost:${process.env.PORT || 3000}`;
    const paymentUrl = `${baseUrl}/pay/${payment_link_id}`;

    res.json({
      success: true,
      payment_id,
      payment_link_id,
      payment_url: paymentUrl,
      amount,
      currency: currency || 'USD',
      created_at: new Date().toISOString()
    });

  } catch (error) {
    console.error('❌ Create Payment Link Error:', error);
    res.status(500).json({
      success: false,
      error: 'Failed to create payment link',
      message: error.message
    });
  }
});

// Get payment details by link ID
router.get('/payment/:payment_link_id', async (req, res) => {
  try {
    const { payment_link_id } = req.params;

    const payment = await database.getPaymentByLinkId(payment_link_id);

    if (!payment) {
      return res.status(404).json({
        success: false,
        error: 'Payment not found'
      });
    }

    // Don't expose sensitive data
    const publicData = {
      payment_link_id: payment.payment_link_id,
      amount: payment.amount,
      currency: payment.currency,
      status: payment.status,
      created_at: payment.created_at
    };

    res.json({
      success: true,
      payment: publicData
    });

  } catch (error) {
    console.error('❌ Get Payment Error:', error);
    res.status(500).json({
      success: false,
      error: 'Failed to retrieve payment',
      message: error.message
    });
  }
});

// Create Stripe Payment Intent
router.post('/create-payment-intent', async (req, res) => {
  try {
    const { payment_link_id } = req.body;

    if (!payment_link_id) {
      return res.status(400).json({
        success: false,
        error: 'payment_link_id is required'
      });
    }

    // Get payment from database
    const payment = await database.getPaymentByLinkId(payment_link_id);

    if (!payment) {
      return res.status(404).json({
        success: false,
        error: 'Payment not found'
      });
    }

    // Check if payment already completed
    if (payment.status === 'completed' || payment.status === 'processing') {
      return res.status(400).json({
        success: false,
        error: 'Payment already processed'
      });
    }

    // Create Stripe Payment Intent
    const result = await stripeService.createPaymentIntent(
      payment.amount,
      payment.currency,
      {
        payment_id: payment.payment_id,
        payment_link_id: payment.payment_link_id
      }
    );

    if (!result.success) {
      await database.logEvent(
        payment.payment_id,
        'payment_intent_creation_failed',
        result,
        'error',
        result.error
      );

      return res.status(500).json({
        success: false,
        error: 'Failed to create payment intent',
        message: result.error
      });
    }

    // Update payment with Stripe Payment Intent ID
    await database.updatePayment(payment.payment_id, {
      stripe_payment_intent_id: result.paymentIntentId,
      status: 'pending_payment'
    });

    // Log event
    await database.logEvent(
      payment.payment_id,
      'payment_intent_created',
      {
        payment_intent_id: result.paymentIntentId,
        amount: result.amount
      }
    );

    res.json({
      success: true,
      clientSecret: result.clientSecret,
      paymentIntentId: result.paymentIntentId,
      amount: payment.amount,
      currency: payment.currency
    });

  } catch (error) {
    console.error('❌ Create Payment Intent Error:', error);
    res.status(500).json({
      success: false,
      error: 'Failed to create payment intent',
      message: error.message
    });
  }
});

// Get all payments (for admin dashboard)
router.get('/payments', async (req, res) => {
  try {
    const { status, limit } = req.query;

    const filters = {};
    if (status) filters.status = status;
    if (limit) filters.limit = parseInt(limit);

    const payments = await database.getAllPayments(filters);

    res.json({
      success: true,
      count: payments.length,
      payments
    });

  } catch (error) {
    console.error('❌ Get Payments Error:', error);
    res.status(500).json({
      success: false,
      error: 'Failed to retrieve payments',
      message: error.message
    });
  }
});

// Get payment logs
router.get('/payment/:payment_id/logs', async (req, res) => {
  try {
    const { payment_id } = req.params;

    const logs = await database.getPaymentLogs(payment_id);

    res.json({
      success: true,
      count: logs.length,
      logs
    });

  } catch (error) {
    console.error('❌ Get Payment Logs Error:', error);
    res.status(500).json({
      success: false,
      error: 'Failed to retrieve payment logs',
      message: error.message
    });
  }
});

// Retry USDT conversion for a payment
router.post('/payment/:payment_id/retry-conversion', async (req, res) => {
  try {
    const { payment_id } = req.params;

    const result = await usdtConversionService.retryConversion(payment_id);

    if (!result.success) {
      return res.status(400).json({
        success: false,
        error: result.error
      });
    }

    res.json({
      success: true,
      message: 'USDT conversion completed',
      result
    });

  } catch (error) {
    console.error('❌ Retry Conversion Error:', error);
    res.status(500).json({
      success: false,
      error: 'Failed to retry conversion',
      message: error.message
    });
  }
});

// Get conversion summary
router.get('/payment/:payment_id/conversion', async (req, res) => {
  try {
    const { payment_id } = req.params;

    const summary = await usdtConversionService.getConversionSummary(payment_id);

    if (!summary.success) {
      return res.status(404).json({
        success: false,
        error: summary.error
      });
    }

    res.json(summary);

  } catch (error) {
    console.error('❌ Get Conversion Summary Error:', error);
    res.status(500).json({
      success: false,
      error: 'Failed to retrieve conversion summary',
      message: error.message
    });
  }
});

// Get wallet balances (Binance USDT balance and total USDT received)
router.get('/wallet-balance', async (req, res) => {
  try {
    const binanceService = require('../services/binance');
    
    // Get Binance USDT balance
    const binanceBalance = await binanceService.getBalance('USDT');
    
    // Calculate total USDT received from completed payments
    const payments = await database.getAllPayments();
    const completedPayments = payments.filter(p => p.status === 'completed');
    const totalUSDTReceived = completedPayments.reduce((sum, p) => {
      return sum + (parseFloat(p.usdt_amount) || 0);
    }, 0);

    res.json({
      success: true,
      binance: {
        available: binanceBalance.balance || 0,
        locked: binanceBalance.locked || 0,
        total: binanceBalance.total || 0,
        configured: binanceBalance.success
      },
      totalReceived: totalUSDTReceived,
      network: 'TRC20'
    });

  } catch (error) {
    console.error('❌ Get Wallet Balance Error:', error);
    res.status(500).json({
      success: false,
      error: 'Failed to retrieve wallet balance',
      message: error.message
    });
  }
});

module.exports = router;
