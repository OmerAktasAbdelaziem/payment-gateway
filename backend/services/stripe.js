const stripe = require('stripe')(process.env.STRIPE_SECRET_KEY);

class StripeService {
  constructor() {
    this.stripe = stripe;
  }

  // Create a Payment Intent
  async createPaymentIntent(amount, currency = 'usd', metadata = {}) {
    try {
      const paymentIntent = await this.stripe.paymentIntents.create({
        amount: Math.round(amount * 100), // Convert to cents
        currency: currency.toLowerCase(),
        metadata: metadata,
        automatic_payment_methods: {
          enabled: true,
        },
      });

      return {
        success: true,
        clientSecret: paymentIntent.client_secret,
        paymentIntentId: paymentIntent.id,
        amount: paymentIntent.amount,
        currency: paymentIntent.currency
      };
    } catch (error) {
      console.error('❌ Stripe Payment Intent Error:', error.message);
      return {
        success: false,
        error: error.message
      };
    }
  }

  // Retrieve a Payment Intent
  async getPaymentIntent(paymentIntentId) {
    try {
      const paymentIntent = await this.stripe.paymentIntents.retrieve(paymentIntentId);
      return {
        success: true,
        paymentIntent
      };
    } catch (error) {
      console.error('❌ Stripe Retrieve Error:', error.message);
      return {
        success: false,
        error: error.message
      };
    }
  }

  // Verify webhook signature
  verifyWebhookSignature(payload, signature) {
    try {
      const event = this.stripe.webhooks.constructEvent(
        payload,
        signature,
        process.env.STRIPE_WEBHOOK_SECRET
      );
      return {
        success: true,
        event
      };
    } catch (error) {
      console.error('❌ Webhook Signature Verification Failed:', error.message);
      return {
        success: false,
        error: error.message
      };
    }
  }

  // Calculate Stripe fee (2.9% + $0.30)
  calculateStripeFee(amount) {
    const percentage = 0.029; // 2.9%
    const fixed = 0.30;
    return (amount * percentage) + fixed;
  }

  // Calculate amount after Stripe fee
  calculateNetAmount(amount) {
    const fee = this.calculateStripeFee(amount);
    return amount - fee;
  }

  // Create a refund
  async createRefund(paymentIntentId, amount = null) {
    try {
      const refundData = {
        payment_intent: paymentIntentId
      };

      if (amount) {
        refundData.amount = Math.round(amount * 100); // Convert to cents
      }

      const refund = await this.stripe.refunds.create(refundData);

      return {
        success: true,
        refund
      };
    } catch (error) {
      console.error('❌ Stripe Refund Error:', error.message);
      return {
        success: false,
        error: error.message
      };
    }
  }

  // List recent charges
  async listCharges(limit = 10) {
    try {
      const charges = await this.stripe.charges.list({
        limit
      });

      return {
        success: true,
        charges: charges.data
      };
    } catch (error) {
      console.error('❌ Stripe List Charges Error:', error.message);
      return {
        success: false,
        error: error.message
      };
    }
  }
}

module.exports = new StripeService();
