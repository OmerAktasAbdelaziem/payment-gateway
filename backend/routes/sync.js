const express = require('express');
const router = express.Router();
const Stripe = require('stripe');
const { v4: uuidv4 } = require('uuid');
const database = require('../services/database-mysql');

const stripe = new Stripe(process.env.STRIPE_SECRET_KEY);

// Sync Stripe transactions to local database
router.post('/sync-stripe', async (req, res) => {
  try {
    console.log('🔄 Starting Stripe sync...');

    // Fetch recent payment intents from Stripe (last 100)
    const paymentIntents = await stripe.paymentIntents.list({
      limit: 100
    });

    let syncedCount = 0;
    let updatedCount = 0;

    for (const pi of paymentIntents.data) {
      // Check if payment already exists in database
      const existingPayment = await database.getPaymentByStripeId(pi.id);

      if (existingPayment) {
        // Update existing payment
        await database.updatePayment(existingPayment.payment_id, {
          status: pi.status === 'succeeded' ? 'completed' : pi.status,
          stripe_payment_intent_id: pi.id,
          amount: pi.amount / 100, // Stripe amounts are in cents
          currency: pi.currency.toUpperCase()
        });
        updatedCount++;
      } else {
        // Create new payment record
        const payment_id = uuidv4();
        const payment_link_id = uuidv4();

        await database.createPayment({
          payment_id,
          payment_link_id,
          amount: pi.amount / 100,
          currency: pi.currency.toUpperCase(),
          customer_email: pi.receipt_email || 'N/A',
          customer_name: pi.metadata?.customer_name || 'N/A',
          status: pi.status === 'succeeded' ? 'completed' : pi.status,
          stripe_payment_intent_id: pi.id
        });
        syncedCount++;
      }
    }

    console.log(`✅ Sync complete: ${syncedCount} new, ${updatedCount} updated`);

    res.json({
      success: true,
      message: 'Stripe transactions synced successfully',
      synced: syncedCount,
      updated: updatedCount,
      total: paymentIntents.data.length
    });

  } catch (error) {
    console.error('❌ Sync failed:', error);
    res.status(500).json({
      success: false,
      error: error.message
    });
  }
});

module.exports = router;
