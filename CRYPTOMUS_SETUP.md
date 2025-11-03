# 🚀 Cryptomus Payment Gateway - Setup Guide

## ✅ What's Been Implemented

Your Cryptomus payment gateway is now fully integrated! Here's what we've done:

### 1. **Backend Services**
- ✅ `CryptomusService.php` - Complete Cryptomus API integration
  - MD5 signature authentication
  - Invoice creation with all parameters
  - Webhook signature verification
  - Error handling and logging

- ✅ `cryptomus.php` - API endpoint handler
  - `/api/cryptomus/create-invoice/{payment_id}` - Creates payment
  - `/api/cryptomus/webhook` - Handles payment callbacks
  - Database integration for payment tracking

### 2. **Frontend**
- ✅ `pay.html` - Updated payment page
  - Now uses Cryptomus as main payment method
  - Clean "Pay with Crypto / Card" button
  - Auto-redirects to hosted payment page

### 3. **Configuration**
- ✅ `.env` - Cryptomus credentials (partially configured)
- ✅ `config.php` - Constants defined
- ✅ `.htaccess` - Routing configured for `/api/cryptomus/*`
- ✅ `success.html` - Payment success page

### 4. **Testing**
- ✅ `test-cryptomus.php` - Test script included

---

## 🔧 REQUIRED: Complete Setup

### Step 1: Get Your Cryptomus Credentials

You need **TWO** pieces of information from Cryptomus:

1. **Merchant UUID** (Merchant ID)
2. **Payment API Key**

#### How to Get Them:

1. Go to: https://app.cryptomus.com/settings
2. Navigate to **API Keys** section
3. Find your **Merchant UUID** - looks like: `8b03432e-385b-4670-8d06-064591096795`
4. Create or find your **Payment API Key** (NOT the Payout key - that's different!)

### Step 2: Update Your `.env` File

Open `d:\payment-gateway\.env` and replace these values:

```env
# Update these two lines:
CRYPTOMUS_MERCHANT_ID=YOUR_MERCHANT_UUID_HERE
CRYPTOMUS_PAYMENT_API_KEY=YOUR_PAYMENT_API_KEY_HERE

# This is already set (Payout key you provided):
CRYPTOMUS_PAYOUT_API_KEY=guE5ae7Kx95BHI3Ecuw3gNLShOhJE1LDxk11qbjOLkf1PZzN7W5HwFDqqbXxHswDXwy9EZAHQI5ZxYrhNfZcqR6lVHkXMrTFsarX4WB9p6FX9EaGLjD86RFxkRS7kmEb
```

**Important Notes:**
- `CRYPTOMUS_MERCHANT_ID` = Your Merchant UUID from Cryptomus dashboard
- `CRYPTOMUS_PAYMENT_API_KEY` = Your Payment API key (for creating invoices)
- `CRYPTOMUS_PAYOUT_API_KEY` = Your Payout API key (already set - for withdrawals later)

### Step 3: Test the Integration

1. Upload all files to your server (if not already done)
2. Visit: `https://internationalitpro.com/test-cryptomus.php`
3. Check the test results:
   - ✅ Configuration should show all keys set
   - ✅ Service should initialize
   - ✅ Test invoice should be created
   - 🔗 Click the payment link to test checkout

### Step 4: Verify Payment Flow

1. Create a test payment link in your admin panel
2. Visit the payment page: `https://internationalitpro.com/pay.html?payment=XXX`
3. Click "Pay with Crypto / Card"
4. Complete a small test payment
5. Verify:
   - Payment redirects to Cryptomus hosted page ✅
   - Payment status updates in your database ✅
   - Webhook is received (check logs) ✅
   - Success page displays after payment ✅

---

## 📁 Files Modified/Created

### New Files:
- `php-backend/CryptomusService.php` - Main service class
- `php-backend/cryptomus.php` - API endpoint handler
- `test-cryptomus.php` - Testing script
- `CRYPTOMUS_SETUP.md` - This guide

### Modified Files:
- `.env` - Added Cryptomus configuration
- `php-backend/config.php` - Added Cryptomus constants
- `.htaccess` - Added Cryptomus routing
- `frontend/pay.html` - Updated to use Cryptomus

---

## 🔍 How It Works

### Payment Flow:

1. **Customer** visits payment page: `/pay.html?payment=123`
2. **Frontend** loads payment details and shows "Pay with Crypto / Card" button
3. **Customer** clicks button
4. **Frontend** calls: `POST /api/cryptomus/create-invoice/123`
5. **Backend** (`cryptomus.php`):
   - Fetches payment amount from database
   - Calls `CryptomusService->createInvoice()`
   - Returns Cryptomus hosted payment URL
6. **Frontend** redirects customer to Cryptomus payment page
7. **Customer** completes payment on Cryptomus
8. **Cryptomus** sends webhook to: `/api/cryptomus/webhook`
9. **Backend** verifies signature and updates payment status
10. **Customer** redirected to success page

### Webhook Authentication:

Cryptomus sends a signature in the `sign` header:
```
sign = md5(base64_encode(json_body) + API_KEY)
```

Our service verifies this signature to ensure the webhook is genuine.

---

## 🔒 Security Features

✅ **MD5 Signature Verification** - All webhooks verified  
✅ **HTTPS Only** - Payment URLs use SSL  
✅ **Database Validation** - Payment links verified before processing  
✅ **Unique Order IDs** - Format: `INTL-{timestamp}-{payment_id}`  
✅ **Status Mapping** - Cryptomus statuses mapped to internal statuses  

---

## 💰 Supported Features

### Payment Methods (via Cryptomus):
- Bitcoin (BTC)
- Ethereum (ETH)
- USDT (TRC20, ERC20, BSC)
- USDC
- Litecoin (LTC)
- And 100+ other cryptocurrencies
- **Credit/Debit Cards** (if enabled in Cryptomus)

### Features:
- ✅ Multiple cryptocurrencies
- ✅ Multiple fiat currencies (USD, EUR, GBP, etc.)
- ✅ Automatic conversion
- ✅ Hosted payment page (no crypto integration needed on frontend)
- ✅ Real-time webhooks
- ✅ Payment expiration (default: 30 minutes)
- ✅ Overpayment/underpayment handling

---

## 📊 Testing Commands

### Test Configuration:
```bash
curl https://internationalitpro.com/test-cryptomus.php
```

### Create Test Invoice:
```bash
curl -X POST https://internationalitpro.com/api/cryptomus/create-invoice/TEST123
```

### Check Logs:
```bash
tail -f error_log
```

---

## 🐛 Troubleshooting

### Issue: "Merchant ID not set"
**Solution:** Update `CRYPTOMUS_MERCHANT_ID` in `.env` file

### Issue: "Invalid signature" in webhook
**Solution:** Verify `CRYPTOMUS_PAYMENT_API_KEY` is correct (not Payout key!)

### Issue: "Invoice creation failed"
**Possible causes:**
- Wrong Merchant UUID
- Wrong API key
- API key doesn't have "Payment" permissions
- Cryptomus account not activated

### Issue: Payment status not updating
**Check:**
1. Webhook URL is accessible: `https://internationalitpro.com/api/cryptomus/webhook`
2. Webhook is configured in Cryptomus dashboard
3. Check error logs for webhook failures

---

## 📝 Next Steps

1. ✅ Get Merchant UUID and Payment API Key from Cryptomus
2. ✅ Update `.env` file with credentials
3. ✅ Run `test-cryptomus.php` to verify setup
4. ✅ Make a small test payment ($1-5)
5. ✅ Verify webhook is received and status updates
6. ✅ Configure Cryptomus webhook URL in their dashboard: `https://internationalitpro.com/api/cryptomus/webhook`
7. 🚀 Go live!

---

## 📞 Support

**Cryptomus Documentation:** https://doc.cryptomus.com/  
**Cryptomus Dashboard:** https://app.cryptomus.com/  
**Cryptomus Support:** support@cryptomus.com

---

## ⚡ Quick Reference

### API Endpoints:
- Create Invoice: `POST /api/cryptomus/create-invoice/{payment_id}`
- Webhook: `POST /api/cryptomus/webhook`

### Configuration:
```env
CRYPTOMUS_MERCHANT_ID=your-uuid
CRYPTOMUS_PAYMENT_API_KEY=your-key
CRYPTOMUS_PAYOUT_API_KEY=already-set
BASE_URL=https://internationalitpro.com
```

### Status Mapping:
| Cryptomus Status | Our Status  |
|------------------|-------------|
| `paid`           | `completed` |
| `paid_over`      | `completed` |
| `process`        | `processing`|
| `fail`           | `failed`    |
| `cancel`         | `failed`    |

---

**Your Cryptomus integration is ready! Just add your Merchant UUID and Payment API Key to go live! 🚀**
