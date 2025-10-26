const binanceService = require('./binance');
const database = require('./database-mysql');

class USDTConversionService {
  
  // Process payment and convert to USDT
  async processPayment(payment) {
    try {
      console.log(`\n💰 Starting USDT conversion for payment: ${payment.payment_id}`);

      // Step 1: Calculate net amount after Stripe fee
      const stripeAmount = parseFloat(payment.amount);
      const stripeFee = parseFloat(payment.stripe_fee) || 0;
      const netAmount = stripeAmount - stripeFee;

      await database.logEvent(
        payment.payment_id,
        'usdt_conversion_started',
        {
          stripe_amount: stripeAmount,
          stripe_fee: stripeFee,
          net_amount: netAmount
        }
      );

      // Step 2: Get current USDT price
      const priceData = await binanceService.getUSDTPrice();
      console.log(`📊 Current USDT price: $${priceData.price}`);

      // Step 3: Buy USDT
      console.log(`💱 Buying USDT with $${netAmount.toFixed(2)}...`);
      const buyResult = await binanceService.buyUSDT(netAmount);

      if (!buyResult.success) {
        throw new Error(`Failed to buy USDT: ${buyResult.error}`);
      }

      const usdtPurchased = buyResult.usdtAmount;
      const exchangeFee = buyResult.fee || 0;

      console.log(`✅ Purchased ${usdtPurchased.toFixed(2)} USDT (fee: ${exchangeFee.toFixed(4)})`);

      await database.logEvent(
        payment.payment_id,
        'usdt_purchased',
        {
          usd_amount: netAmount,
          usdt_amount: usdtPurchased,
          exchange_fee: exchangeFee,
          order_id: buyResult.orderId,
          simulated: buyResult.simulated || false
        }
      );

      // Step 4: Get withdrawal address
      const walletAddress = process.env.USDT_WALLET_ADDRESS;
      const network = process.env.USDT_NETWORK || 'TRC20';

      if (!walletAddress || walletAddress === 'your_usdt_wallet_address_here') {
        console.log('⚠️  USDT wallet not configured. Skipping withdrawal.');
        
        // Update payment record
        await database.updatePayment(payment.payment_id, {
          status: 'completed',
          usdt_amount: usdtPurchased,
          exchange_fee: exchangeFee,
          exchange_order_id: buyResult.orderId,
          total_fees: stripeFee + exchangeFee,
          completed_at: new Date().toISOString()
        });

        await database.logEvent(
          payment.payment_id,
          'usdt_conversion_completed',
          {
            status: 'completed_no_withdrawal',
            reason: 'Wallet address not configured'
          }
        );

        return {
          success: true,
          payment_id: payment.payment_id,
          usdt_amount: usdtPurchased,
          status: 'completed_no_withdrawal'
        };
      }

      // Step 5: Withdraw USDT to wallet
      const networkFee = binanceService.getNetworkFee(network);
      const withdrawAmount = usdtPurchased - networkFee;

      if (withdrawAmount <= 0) {
        throw new Error('Insufficient USDT amount after network fees');
      }

      console.log(`📤 Withdrawing ${withdrawAmount.toFixed(2)} USDT to ${walletAddress} (${network})...`);

      const withdrawResult = await binanceService.withdrawUSDT(
        withdrawAmount,
        walletAddress,
        network
      );

      if (!withdrawResult.success) {
        throw new Error(`Failed to withdraw USDT: ${withdrawResult.error}`);
      }

      console.log(`✅ Withdrawal initiated: ${withdrawResult.txId}`);
      console.log(`📝 Network: ${network}, Fee: ${networkFee} USDT`);

      await database.logEvent(
        payment.payment_id,
        'usdt_withdrawn',
        {
          amount: withdrawAmount,
          address: walletAddress,
          network: network,
          tx_id: withdrawResult.txId,
          network_fee: networkFee,
          simulated: withdrawResult.simulated || false
        }
      );

      // Step 6: Update payment record
      const totalFees = stripeFee + exchangeFee + networkFee;

      await database.updatePayment(payment.payment_id, {
        status: 'completed',
        usdt_amount: withdrawAmount,
        usdt_transaction_hash: withdrawResult.txId,
        exchange_order_id: buyResult.orderId,
        exchange_fee: exchangeFee,
        network_fee: networkFee,
        total_fees: totalFees,
        completed_at: new Date().toISOString()
      });

      await database.logEvent(
        payment.payment_id,
        'usdt_conversion_completed',
        {
          total_usdt: withdrawAmount,
          total_fees: totalFees,
          tx_id: withdrawResult.txId
        }
      );

      console.log(`\n🎉 USDT Conversion Complete!`);
      console.log(`   Original Amount: $${stripeAmount.toFixed(2)}`);
      console.log(`   Total Fees: $${totalFees.toFixed(2)}`);
      console.log(`   USDT Received: ${withdrawAmount.toFixed(2)} USDT`);
      console.log(`   Transaction: ${withdrawResult.txId}\n`);

      return {
        success: true,
        payment_id: payment.payment_id,
        original_amount: stripeAmount,
        usdt_amount: withdrawAmount,
        total_fees: totalFees,
        transaction_hash: withdrawResult.txId,
        network: network
      };

    } catch (error) {
      console.error(`\n❌ USDT Conversion Error for ${payment.payment_id}:`, error.message);

      // Log error
      await database.logEvent(
        payment.payment_id,
        'usdt_conversion_failed',
        { error: error.message },
        'error',
        error.message
      );

      // Update payment status
      await database.updatePayment(payment.payment_id, {
        status: 'failed_conversion'
      });

      return {
        success: false,
        payment_id: payment.payment_id,
        error: error.message
      };
    }
  }

  // Retry failed conversion
  async retryConversion(paymentId) {
    try {
      const payment = await database.getPaymentById(paymentId);

      if (!payment) {
        throw new Error('Payment not found');
      }

      if (payment.status === 'completed') {
        throw new Error('Payment already completed');
      }

      console.log(`🔄 Retrying USDT conversion for payment: ${paymentId}`);

      return await this.processPayment(payment);

    } catch (error) {
      console.error('❌ Retry conversion error:', error.message);
      return {
        success: false,
        error: error.message
      };
    }
  }

  // Get conversion summary
  async getConversionSummary(paymentId) {
    try {
      const payment = await database.getPaymentById(paymentId);
      const logs = await database.getPaymentLogs(paymentId);

      if (!payment) {
        throw new Error('Payment not found');
      }

      return {
        success: true,
        payment: {
          id: payment.payment_id,
          amount: payment.amount,
          currency: payment.currency,
          status: payment.status,
          usdt_amount: payment.usdt_amount,
          transaction_hash: payment.usdt_transaction_hash,
          fees: {
            stripe_fee: payment.stripe_fee,
            exchange_fee: payment.exchange_fee,
            network_fee: payment.network_fee,
            total_fees: payment.total_fees
          },
          created_at: payment.created_at,
          completed_at: payment.completed_at
        },
        logs: logs.map(log => ({
          event: log.event_type,
          status: log.status,
          data: log.event_data ? JSON.parse(log.event_data) : null,
          timestamp: log.created_at
        }))
      };

    } catch (error) {
      console.error('❌ Get conversion summary error:', error.message);
      return {
        success: false,
        error: error.message
      };
    }
  }
}

module.exports = new USDTConversionService();
