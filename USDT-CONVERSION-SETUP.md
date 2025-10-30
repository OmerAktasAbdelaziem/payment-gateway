# 🚀 Automatic USD to USDT Conversion Setup Guide

## Overview
Your payment gateway now supports **automatic conversion** of Stripe USD payments to USDT and withdrawal to your TRC20 wallet.

**Flow:**
1. Customer pays $100 via Stripe → Your Stripe account
2. Webhook triggers → PHP backend
3. Binance API buys USDT with USDC → ~100 USDT
4. Binance withdraws USDT → Your TRC20 wallet `TPgGspfthoVxUsEwTusfh7iK5ts7UXfSjk`

---

## ⚠️ IMPORTANT: Binance Setup Required

### Step 1: Enable Binance API Permissions

1. Go to: https://www.binance.com/en/my/settings/api-management
2. Select your API key or create a new one
3. **Enable these permissions:**
   - ✅ **Enable Spot & Margin Trading** (required for buying USDT)
   - ✅ **Enable Withdrawals** (required for sending USDT to your wallet)
   - ✅ **Enable Reading** (already enabled)

### Step 2: Add Withdrawal Whitelist

1. Go to: https://www.binance.com/en/my/security/withdraw-whitelist
2. Add your USDT wallet address: `TPgGspfthoVxUsEwTusfh7iK5ts7UXfSjk`
3. Select network: **TRC20** (Tron)
4. Verify via 2FA/Email

### Step 3: Fund Your Binance Account with USDC

**Why USDC?** The system converts USDC → USDT (both stablecoins, ~$1 each)

**Two options:**

#### Option A: Transfer from Stripe to Binance (Manual)
1. Withdraw USD from Stripe to your bank account
2. Buy USDC on Coinbase/Binance
3. Transfer USDC to your Binance Spot Wallet

#### Option B: Direct Deposit (Recommended)
1. Go to Binance → Deposit
2. Select **USDC**
3. Choose network (BSC or ERC20)
4. Send USDC directly to your Binance wallet

**Minimum balance:** Keep at least $100 USDC in your Binance account to process conversions

---

## 📋 Installation Steps

### 1. Upload New Files to Server

```bash
# Upload BinanceService.php
scp -P 65002 "d:\payment-gateway\php-backend\BinanceService.php" "u402548537@213.130.145.169:domains/internationalitpro.com/public_html/php-backend/BinanceService.php"

# Upload updated StripeService.php
scp -P 65002 "d:\payment-gateway\php-backend\StripeService.php" "u402548537@213.130.145.169:domains/internationalitpro.com/public_html/php-backend/StripeService.php"

# Upload updated config.php
scp -P 65002 "d:\payment-gateway\php-backend\config.php" "u402548537@213.130.145.169:domains/internationalitpro.com/public_html/php-backend/config.php"

# Upload updated .env
scp -P 65002 "d:\payment-gateway\.env" "u402548537@213.130.145.169:domains/internationalitpro.com/public_html/.env"

# Upload database migration
scp -P 65002 "d:\payment-gateway\php-backend\migrate-usdt.php" "u402548537@213.130.145.169:domains/internationalitpro.com/public_html/php-backend/migrate-usdt.php"
```

### 2. Run Database Migration

```bash
# SSH into your server
ssh -p 65002 u402548537@213.130.145.169

# Navigate to php-backend directory
cd domains/internationalitpro.com/public_html/php-backend

# Run migration
php migrate-usdt.php
```

Expected output:
```
🔄 Creating USDT conversions table...
✅ Table 'usdt_conversions' created successfully!
```

### 3. Enable Auto-Conversion

Edit `.env` file on server:
```bash
# Change this line:
AUTO_CONVERT_TO_USDT=false

# To:
AUTO_CONVERT_TO_USDT=true
```

---

## 🧪 Testing

### Test with Small Amount First!

1. Create a test payment link for $5
2. Complete the payment with a test card
3. Check server logs:
   ```bash
   tail -f domains/internationalitpro.com/logs/error_log
   ```
4. Look for:
   ```
   Starting USDT conversion for payment PAY_XXX: $5
   USDT conversion successful: 5.00 USDT sent to TPgG...
   ```

### Check Conversion Status

View conversions in database:
```sql
SELECT * FROM usdt_conversions ORDER BY created_at DESC LIMIT 10;
```

---

## 📊 Monitoring

### Admin Dashboard Updates (Optional)

You can add a "USDT Conversions" section to your admin dashboard to track:
- Total USD converted
- Total USDT received
- Failed conversions
- Wallet balance

---

## ⚙️ Configuration

### Current Settings (.env)

```bash
# Binance API Keys
EXCHANGE_API_KEY=mDEkHvI7RR2Yjipem9COGpTMhLqOcdRv4wfzjOO08nSR1AbM5w2mB6QF3fCoeJPT
EXCHANGE_API_SECRET=HYvHUhQ1EW0bKAJXiyT9uGLd3JUQdKvVhNL3Jvb2Lu2fYpg6y1bnFYDXPxemzGPA

# USDT Wallet
USDT_WALLET_ADDRESS=TPgGspfthoVxUsEwTusfh7iK5ts7UXfSjk
USDT_NETWORK=TRC20

# Auto-conversion toggle
AUTO_CONVERT_TO_USDT=false  # Change to 'true' to enable
```

---

## 💰 Fees

**Binance Trading Fee:** ~0.1% (buy USDT with USDC)
**Binance Withdrawal Fee (TRC20):** ~$1 USDT flat fee

**Example:**
- Customer pays: $100
- Binance buys: ~99.90 USDT (after 0.1% fee)
- You receive: ~98.90 USDT (after $1 withdrawal fee)

**Net cost:** ~1.1% per transaction

---

## 🔒 Security Notes

1. **Never commit .env to Git** - API keys are sensitive
2. **Keep Binance 2FA enabled** - Protects withdrawals
3. **Monitor conversion logs** - Watch for failed transactions
4. **Limit API key permissions** - Only enable what's needed
5. **Use IP whitelist on Binance** - Restrict API access to your server IP

---

## 🐛 Troubleshooting

### "Insufficient USDC balance"
**Solution:** Deposit more USDC to your Binance Spot Wallet

### "Withdrawal address not whitelisted"
**Solution:** Add `TPgGspfthoVxUsEwTusfh7iK5ts7UXfSjk` to Binance withdrawal whitelist

### "API key does not have withdrawal permission"
**Solution:** Enable "Enable Withdrawals" in Binance API settings

### "Network congestion"
**Solution:** TRC20 withdrawals usually complete in 1-5 minutes. Check Tronscan.org

---

## 📞 Support

If you encounter issues:
1. Check server error logs: `tail -f domains/internationalitpro.com/logs/error_log`
2. Check Binance API status: https://www.binance.com/en/support/announcement
3. Check database: `SELECT * FROM usdt_conversions WHERE status = 'failed'`

---

## 🎯 Next Steps

1. ✅ Complete Binance API setup
2. ✅ Fund Binance account with USDC
3. ✅ Upload all files to server
4. ✅ Run database migration
5. ✅ Test with small amount ($5-10)
6. ✅ Enable AUTO_CONVERT_TO_USDT=true
7. ✅ Monitor first few transactions
8. ✅ Scale up to production

---

**Status:** Ready to deploy! Just need to complete Binance setup and fund USDC.
