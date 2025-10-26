const Binance = require('binance-api-node').default;

class BinanceService {
  constructor() {
    this.client = null;
    this.isConfigured = false;
    this.initialize();
  }

  // Initialize Binance client
  initialize() {
    try {
      const apiKey = process.env.EXCHANGE_API_KEY;
      const apiSecret = process.env.EXCHANGE_API_SECRET;

      if (!apiKey || !apiSecret || 
          apiKey === 'your_binance_api_key_here' || 
          apiSecret === 'your_binance_api_secret_here') {
        console.log('⚠️  Binance API not configured. USDT conversion will be simulated.');
        this.isConfigured = false;
        return;
      }

      this.client = Binance({
        apiKey: apiKey,
        apiSecret: apiSecret,
      });

      this.isConfigured = true;
      console.log('✅ Binance API configured');

    } catch (error) {
      console.error('❌ Binance initialization error:', error.message);
      this.isConfigured = false;
    }
  }

  // Check if Binance is configured
  isReady() {
    return this.isConfigured && this.client !== null;
  }

  // Get account balance
  async getBalance(asset = 'USDT') {
    try {
      if (!this.isReady()) {
        return {
          success: false,
          error: 'Binance API not configured',
          balance: 0
        };
      }

      const accountInfo = await this.client.accountInfo();
      const assetBalance = accountInfo.balances.find(b => b.asset === asset);

      return {
        success: true,
        balance: parseFloat(assetBalance?.free || 0),
        locked: parseFloat(assetBalance?.locked || 0),
        total: parseFloat(assetBalance?.free || 0) + parseFloat(assetBalance?.locked || 0)
      };

    } catch (error) {
      console.error('❌ Get balance error:', error.message);
      return {
        success: false,
        error: error.message,
        balance: 0
      };
    }
  }

  // Get current USDT price
  async getUSDTPrice() {
    try {
      if (!this.isReady()) {
        return {
          success: true,
          price: 1.00, // Default USDT price
          symbol: 'USDTUSD'
        };
      }

      // Get USDT/BUSD price as proxy for USD
      const ticker = await this.client.prices({ symbol: 'USDTBUSD' });
      const price = parseFloat(ticker.USDTBUSD || 1.00);

      return {
        success: true,
        price: price,
        symbol: 'USDTUSD'
      };

    } catch (error) {
      console.error('❌ Get USDT price error:', error.message);
      return {
        success: true,
        price: 1.00, // Fallback to 1:1
        symbol: 'USDTUSD'
      };
    }
  }

  // Buy USDT with USD (simulated - requires fiat deposit in real scenario)
  async buyUSDT(usdAmount) {
    try {
      if (!this.isReady()) {
        // Simulate purchase for testing
        console.log('💱 [SIMULATED] Buying USDT...');
        return {
          success: true,
          simulated: true,
          usdtAmount: usdAmount * 0.998, // Simulate 0.2% fee
          orderId: 'SIMULATED_' + Date.now(),
          fee: usdAmount * 0.002,
          price: 1.00
        };
      }

      // In real implementation, you would:
      // 1. Check if you have USD balance on Binance
      // 2. Place a market order to buy USDT
      // 3. Wait for order to fill
      
      // For now, simulate the purchase
      const priceData = await this.getUSDTPrice();
      const usdtAmount = usdAmount / priceData.price;
      const fee = usdtAmount * 0.001; // 0.1% Binance fee

      console.log(`💱 [INFO] Would buy ${usdtAmount.toFixed(2)} USDT with $${usdAmount}`);

      return {
        success: true,
        simulated: true,
        usdtAmount: usdtAmount - fee,
        orderId: 'BUY_' + Date.now(),
        fee: fee,
        price: priceData.price,
        note: 'In production, implement actual market order placement'
      };

    } catch (error) {
      console.error('❌ Buy USDT error:', error.message);
      return {
        success: false,
        error: error.message
      };
    }
  }

  // Withdraw USDT to external wallet
  async withdrawUSDT(amount, address, network = 'TRC20') {
    try {
      if (!this.isReady()) {
        // Simulate withdrawal for testing
        console.log('📤 [SIMULATED] Withdrawing USDT...');
        return {
          success: true,
          simulated: true,
          amount: amount,
          address: address,
          network: network,
          txId: 'SIMULATED_TX_' + Date.now(),
          fee: network === 'TRC20' ? 1 : (network === 'BEP20' ? 0.5 : 10),
          status: 'pending'
        };
      }

      // Validate address
      if (!address || address === 'your_usdt_wallet_address_here') {
        throw new Error('Invalid USDT wallet address');
      }

      // Map network names
      const networkMap = {
        'TRC20': 'TRX',
        'BEP20': 'BSC',
        'ERC20': 'ETH'
      };

      const binanceNetwork = networkMap[network] || 'TRX';

      // In production, uncomment this to make actual withdrawal
      /*
      const withdrawal = await this.client.withdraw({
        asset: 'USDT',
        address: address,
        amount: amount,
        network: binanceNetwork
      });

      return {
        success: true,
        amount: amount,
        address: address,
        network: network,
        txId: withdrawal.id,
        status: 'pending'
      };
      */

      // For now, simulate
      console.log(`📤 [INFO] Would withdraw ${amount} USDT to ${address} via ${network}`);

      return {
        success: true,
        simulated: true,
        amount: amount,
        address: address,
        network: network,
        txId: 'WITHDRAW_' + Date.now(),
        fee: network === 'TRC20' ? 1 : (network === 'BEP20' ? 0.5 : 10),
        status: 'pending',
        note: 'In production, uncomment actual withdrawal code'
      };

    } catch (error) {
      console.error('❌ Withdraw USDT error:', error.message);
      return {
        success: false,
        error: error.message
      };
    }
  }

  // Get withdrawal history
  async getWithdrawalHistory(limit = 10) {
    try {
      if (!this.isReady()) {
        return {
          success: false,
          error: 'Binance API not configured',
          withdrawals: []
        };
      }

      const withdrawals = await this.client.withdrawHistory({
        asset: 'USDT',
        limit: limit
      });

      return {
        success: true,
        withdrawals: withdrawals
      };

    } catch (error) {
      console.error('❌ Get withdrawal history error:', error.message);
      return {
        success: false,
        error: error.message,
        withdrawals: []
      };
    }
  }

  // Get deposit address
  async getDepositAddress(asset = 'USDT', network = 'TRX') {
    try {
      if (!this.isReady()) {
        return {
          success: false,
          error: 'Binance API not configured'
        };
      }

      const address = await this.client.depositAddress({
        asset: asset,
        network: network
      });

      return {
        success: true,
        address: address.address,
        tag: address.tag,
        network: network
      };

    } catch (error) {
      console.error('❌ Get deposit address error:', error.message);
      return {
        success: false,
        error: error.message
      };
    }
  }

  // Calculate exchange fee
  calculateFee(amount, feePercentage = 0.1) {
    return amount * (feePercentage / 100);
  }

  // Calculate network fee based on network
  getNetworkFee(network = 'TRC20') {
    const fees = {
      'TRC20': 1.0,    // ~$1
      'BEP20': 0.5,    // ~$0.50
      'ERC20': 15.0,   // ~$15 (high gas fees)
      'POLYGON': 0.1   // ~$0.10
    };

    return fees[network] || 1.0;
  }
}

module.exports = new BinanceService();
