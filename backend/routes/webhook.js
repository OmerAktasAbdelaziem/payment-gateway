const express = require('express');
const router = express.Router();
const database = require('../services/database');
const stripeService = require('../services/stripe');
const usdtConversionService = require('../services/usdtConversion');

// Stripe webhook endpoint
// NOTE: This needs raw body, so we'll handle it specially in server.js
router.post('/webhook', express.raw({ type: 'application/json' }), async (req, res) => {
  const signature = req.headers['stripe-signature'];

  try {
    // Verify webhook signature
    const verification = stripeService.verifyWebhookSignature(req.body, signature);

    if (!verification.success) {
      console.error('❌ Webhook signature verification failed');
      return res.status(400).json({
        success: false,
        error: 'Webhook signature verification failed'
      });
    }

    const event = verification.event;
    console.log('📨 Webhook received:', event.type);

    // Handle different event types
    switch (event.type) {
      case 'payment_intent.succeeded':
        await handlePaymentSuccess(event.data.object);
        break;

      case 'payment_intent.payment_failed':
        await handlePaymentFailed(event.data.object);
        break;

      case 'payment_intent.canceled':
        await handlePaymentCanceled(event.data.object);
        break;

      case 'charge.refunded':
        await handleRefund(event.data.object);
        break;

      default:
        console.log(`ℹ️  Unhandled event type: ${event.type}`);
    }

    // Return success response
    res.json({ received: true });

  } catch (error) {
    console.error('❌ Webhook Error:', error);
    res.status(500).json({
      success: false,
      error: 'Webhook processing failed',
      message: error.message
    });
  }
});

// Handle successful payment
async function handlePaymentSuccess(paymentIntent) {
  try {
    console.log('✅ Payment succeeded:', paymentIntent.id);

    // Get payment from database
    const payment = await database.getPaymentByStripeId(paymentIntent.id);

    if (!payment) {
      console.error('❌ Payment not found in database:', paymentIntent.id);
      return;
    }

    // Calculate fees
    const amount = paymentIntent.amount / 100; // Convert from cents
    const stripeFee = stripeService.calculateStripeFee(amount);
    const netAmount = amount - stripeFee;

    // Update payment status
    await database.updatePayment(payment.payment_id, {
      status: 'processing',
      stripe_fee: stripeFee,
      customer_email: paymentIntent.receipt_email || payment.customer_email
    });

    // Log event
    await database.logEvent(
      payment.payment_id,
      'payment_succeeded',
      {
        payment_intent_id: paymentIntent.id,
        amount: amount,
        stripe_fee: stripeFee,
        net_amount: netAmount
      }
    );

    console.log(`💰 Payment ${payment.payment_id}: $${amount} received (net: $${netAmount.toFixed(2)})`);

    // Trigger USDT conversion
    console.log('🚀 Triggering USDT conversion...');
    
    // Run conversion asynchronously (don't block webhook response)
    usdtConversionService.processPayment(payment)
      .then(result => {
        if (result.success) {
          console.log(`✅ USDT conversion completed for ${payment.payment_id}`);
        } else {
          console.error(`❌ USDT conversion failed for ${payment.payment_id}:`, result.error);
        }
      })
      .catch(error => {
        console.error(`❌ USDT conversion error for ${payment.payment_id}:`, error);
      });

  } catch (error) {
    console.error('❌ Handle Payment Success Error:', error);
    
    // Log error
    if (payment) {
      await database.logEvent(
        payment.payment_id,
        'payment_processing_error',
        { error: error.message },
        'error',
        error.message
      );
    }
  }
}

// Handle failed payment
async function handlePaymentFailed(paymentIntent) {
  try {
    console.log('❌ Payment failed:', paymentIntent.id);

    const payment = await database.getPaymentByStripeId(paymentIntent.id);

    if (!payment) {
      console.error('❌ Payment not found in database:', paymentIntent.id);
      return;
    }

    // Update payment status
    await database.updatePayment(payment.payment_id, {
      status: 'failed'
    });

    // Log event
    await database.logEvent(
      payment.payment_id,
      'payment_failed',
      {
        payment_intent_id: paymentIntent.id,
        error: paymentIntent.last_payment_error?.message
      },
      'error',
      paymentIntent.last_payment_error?.message
    );

  } catch (error) {
    console.error('❌ Handle Payment Failed Error:', error);
  }
}

// Handle canceled payment
async function handlePaymentCanceled(paymentIntent) {
  try {
    console.log('⚠️  Payment canceled:', paymentIntent.id);

    const payment = await database.getPaymentByStripeId(paymentIntent.id);

    if (!payment) {
      console.error('❌ Payment not found in database:', paymentIntent.id);
      return;
    }

    // Update payment status
    await database.updatePayment(payment.payment_id, {
      status: 'canceled'
    });

    // Log event
    await database.logEvent(
      payment.payment_id,
      'payment_canceled',
      { payment_intent_id: paymentIntent.id }
    );

  } catch (error) {
    console.error('❌ Handle Payment Canceled Error:', error);
  }
}

// Handle refund
async function handleRefund(charge) {
  try {
    console.log('💸 Refund processed:', charge.id);

    // Get payment by payment intent
    const payment = await database.getPaymentByStripeId(charge.payment_intent);

    if (!payment) {
      console.error('❌ Payment not found in database:', charge.payment_intent);
      return;
    }

    // Update payment status
    await database.updatePayment(payment.payment_id, {
      status: 'refunded'
    });

    // Log event
    await database.logEvent(
      payment.payment_id,
      'payment_refunded',
      {
        charge_id: charge.id,
        refund_amount: charge.amount_refunded / 100
      }
    );

  } catch (error) {
    console.error('❌ Handle Refund Error:', error);
  }
}

module.exports = router;
