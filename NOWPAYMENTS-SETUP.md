# 🚀 NOWPayments Integration Setup Guide

## Overview
Your payment gateway now uses **NOWPayments** instead of Stripe. Customers can pay with credit/debit cards or cryptocurrency, and you receive USDT directly to your TRC20 wallet.

**Benefits:**
- ✅ **Lower fees:** 0.5% - 1% (vs 4% with Stripe)
- ✅ **Direct to wallet:** USDT sent to `TPgGspfthoVxUsEwTusfh7iK5ts7UXfSjk`
- ✅ **No KYC required** for basic tier
- ✅ **200+ cryptocurrencies** supported
- ✅ **No balance maintenance** required

---

## 📋 Step 1: Create NOWPayments Account

1. **Go to:** https://nowpayments.io/
2. **Click:** "Sign Up" (top right)
3. **Register** with your email
4. **Verify** your email address
5. **Login** to dashboard: https://nowpayments.io/app/dashboard

---

## 🔑 Step 2: Get API Keys

### Get API Key:
1. Go to: https://nowpayments.io/app/dashboard
2. Click **"Settings"** → **"API keys"**
3. Click **"Generate new API key"**
4. **Copy the API key** (starts with something like `ABC123...`)
5. **Save it** - you'll need this for `.env` file

### Get IPN Secret:
1. Go to: https://nowpayments.io/app/settings/ipn
2. Click **"Generate IPN Secret Key"**
3. **Copy the secret** (long random string)
4. **Save it** - you'll need this for `.env` file

### Set IPN Callback URL:
1. Still in IPN settings
2. **Enter callback URL:** `https://internationalitpro.com/api/nowpayments/webhook`
3. **Click "Save"**

---

## 💰 Step 3: Configure Payout Wallet

1. Go to: https://nowpayments.io/app/settings/payouts
2. **Add wallet address:**
   - **Currency:** USDT
   - **Network:** TRC20
   - **Address:** `TPgGspfthoVxUsEwTusfh7iK5ts7UXfSjk`
3. **Enable auto-withdrawal** (optional but recommended)
4. **Click "Save"**

---

## 🛠️ Step 4: Update Configuration Files

### Update `.env` file:

```bash
# Replace these placeholders with your actual keys
NOWPAYMENTS_API_KEY=your_api_key_here
NOWPAYMENTS_IPN_SECRET=your_ipn_secret_here
```

**Example:**
```bash
NOWPAYMENTS_API_KEY=ABC123XYZ789_YOUR_ACTUAL_KEY
NOWPAYMENTS_IPN_SECRET=def456uvw012_YOUR_ACTUAL_SECRET
```

---

## 📤 Step 5: Deploy to Server

Run these commands to upload the new files:

```powershell
# Upload NOWPaymentsService.php
scp -P 65002 "d:\payment-gateway\php-backend\NOWPaymentsService.php" "u402548537@213.130.145.169:domains/internationalitpro.com/public_html/php-backend/NOWPaymentsService.php"

# Upload updated api.php
scp -P 65002 "d:\payment-gateway\php-backend\api.php" "u402548537@213.130.145.169:domains/internationalitpro.com/public_html/php-backend/api.php"

# Upload updated config.php
scp -P 65002 "d:\payment-gateway\php-backend\config.php" "u402548537@213.130.145.169:domains/internationalitpro.com/public_html/php-backend/config.php"

# Upload payment JavaScript
scp -P 65002 "d:\payment-gateway\public\js\payment-nowpayments.js" "u402548537@213.130.145.169:domains/internationalitpro.com/public_html/public/js/payment-nowpayments.js"

# Upload payment HTML
scp -P 65002 "d:\payment-gateway\frontend\pay-nowpayments.html" "u402548537@213.130.145.169:domains/internationalitpro.com/public_html/frontend/pay-nowpayments.html"

# Upload updated .env
scp -P 65002 "d:\payment-gateway\.env" "u402548537@213.130.145.169:domains/internationalitpro.com/public_html/.env"
```

---

## 🧪 Step 6: Test Payment Flow

### Create Test Payment:
1. Login to admin: https://internationalitpro.com/admin
2. Generate a payment link for **$5** (small test amount)
3. Open the payment link
4. Click "Pay Now"
5. You'll be redirected to NOWPayments checkout
6. Complete payment with test card or crypto

### Expected Flow:
```
Customer clicks "Pay Now"
         ↓
System creates NOWPayments invoice
         ↓
Customer redirected to NOWPayments checkout
         ↓
Customer pays with card/crypto
         ↓
NOWPayments processes payment
         ↓
NOWPayments sends USDT to your wallet
         ↓
Webhook updates payment status to "completed"
         ↓
Done! ✅
```

---

## 💸 Fee Structure

**NOWPayments Fees:**
- **Crypto payments:** 0.5%
- **Card payments:** 1% - 2%
- **Network fees:** Varies (TRC20 is cheapest)

**Example:**
- Customer pays: $100
- NOWPayments fee: $1 (1%)
- Network fee: ~$1 (TRC20)
- **You receive:** ~98 USDT

**Much cheaper than Stripe!** (Stripe = 2.9% + $0.30 = ~$3.20 per $100)

---

## 📊 Monitoring & Management

### Check Payments:
- **Dashboard:** https://nowpayments.io/app/payments
- **View all transactions, statuses, and amounts**

### Check Wallet Balance:
- **Tronscan:** https://tronscan.org/#/address/TPgGspfthoVxUsEwTusfh7iK5ts7UXfSjk
- **View USDT balance and transaction history**

### Admin Dashboard:
- **Your admin:** https://internationalitpro.com/admin
- **Track payment statuses in your database**

---

## 🔧 Optional: Update .htaccess

Update the .htaccess file to route to the new payment page:

```apache
# Change from:
RewriteRule ^pay/([A-Z0-9_]+)$ frontend/pay.html [L]

# To:
RewriteRule ^pay/([A-Z0-9_]+)$ frontend/pay-nowpayments.html [L]
```

---

## 🔒 Security Best Practices

1. ✅ **Never commit .env to Git** - Contains API keys
2. ✅ **Enable 2FA on NOWPayments** - Protects your account
3. ✅ **Monitor webhook logs** - Check for failed transactions
4. ✅ **Use HTTPS** - Already configured (internationalitpro.com)
5. ✅ **Test with small amounts** - Before going live

---

## 🐛 Troubleshooting

### "Invalid API key"
**Solution:** Check that you copied the full API key from NOWPayments dashboard

### "Webhook not working"
**Solution:** 
1. Verify callback URL: `https://internationalitpro.com/api/nowpayments/webhook`
2. Check IPN secret is correct in `.env`
3. Check server logs: `tail -f domains/internationalitpro.com/logs/error_log`

### "Payment not updating status"
**Solution:** 
1. Check webhook is configured in NOWPayments settings
2. Verify IPN secret matches
3. Check database for payment status

### "USDT not arriving"
**Solution:** 
1. Check NOWPayments dashboard - payment status
2. Verify wallet address is correct in settings
3. TRC20 transfers usually take 1-5 minutes
4. Check Tronscan: https://tronscan.org/#/address/TPgGspfthoVxUsEwTusfh7iK5ts7UXfSjk

---

## ✅ Checklist

Before going live:

- [ ] Created NOWPayments account
- [ ] Generated API key and IPN secret
- [ ] Added wallet address (TPgGspfthoVxUsEwTusfh7iK5ts7UXfSjk) to NOWPayments
- [ ] Updated `.env` with API keys
- [ ] Uploaded all files to server
- [ ] Tested payment with small amount ($5-10)
- [ ] Verified USDT received in wallet
- [ ] Updated .htaccess to use new payment page
- [ ] Checked webhook is working

---

## 🎉 You're Ready!

Your payment gateway now accepts card and crypto payments with much lower fees, and you receive USDT directly!

**Key Benefits:**
- 💰 Save ~3% in fees compared to Stripe
- ⚡ Receive USDT instantly
- 🌍 Accept 200+ cryptocurrencies
- 🔒 No KYC required
- 🚀 Simpler than Binance auto-convert

**Need help?** Check NOWPayments docs: https://nowpayments.io/help
