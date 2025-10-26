# 💳 Payment Gateway - Card to USDT

A complete payment gateway that accepts credit/debit card payments via Stripe and automatically converts them to USDT cryptocurrency sent to your wallet.

## ✨ Features

- ✅ **Custom Payment Links** - Generate unique payment links for customers
- ✅ **Stripe Integration** - Secure card payment processing
- ✅ **Automatic USDT Conversion** - Converts payments to USDT via Binance
- ✅ **Custom Branding** - Fully customizable payment pages with your design
- ✅ **Admin Dashboard** - Manage payments and view statistics
- ✅ **Real-time Tracking** - Monitor payment status and conversion progress
- ✅ **Multiple Networks** - Support for TRC20, BEP20, ERC20, Polygon
- ✅ **Webhook Support** - Automatic payment notifications
- ✅ **Transaction Logs** - Complete audit trail for all transactions

## 🏗️ Architecture

```
Customer Card Payment → Stripe → Your Server → Binance → USDT to Your Wallet
```

### Flow:
1. Customer receives payment link
2. Customer pays with credit/debit card (Stripe)
3. Stripe charges card and sends webhook
4. Server receives USD in Stripe account
5. Server automatically buys USDT on Binance
6. USDT is withdrawn to your configured wallet
7. Customer receives confirmation

## 📋 Prerequisites

- Node.js 14+ installed
- Stripe account ([Sign up](https://dashboard.stripe.com/register))
- Binance account ([Sign up](https://www.binance.com/en/register))
- USDT wallet address (TrustWallet, MetaMask, etc.)

## 🚀 Quick Start

### 1. Clone and Install

```bash
cd payment-gateway
npm install
```

### 2. Configure Environment

Copy `.env.example` to `.env` and configure:

```env
# Stripe Configuration
STRIPE_SECRET_KEY=sk_test_YOUR_KEY_HERE
STRIPE_PUBLISHABLE_KEY=pk_test_YOUR_KEY_HERE
STRIPE_WEBHOOK_SECRET=whsec_YOUR_SECRET_HERE

# Binance Configuration
EXCHANGE_API_KEY=YOUR_BINANCE_API_KEY
EXCHANGE_API_SECRET=YOUR_BINANCE_API_SECRET

# USDT Wallet
USDT_WALLET_ADDRESS=YOUR_USDT_WALLET_ADDRESS
USDT_NETWORK=TRC20

# Server
PORT=3000
BASE_URL=http://localhost:3000
```

### 3. Start Server

```bash
npm start
```

Server will be running at: `http://localhost:3000`

## 🔑 Getting API Keys

### Stripe API Keys

1. Go to [Stripe Dashboard](https://dashboard.stripe.com/apikeys)
2. Copy your **Publishable key** and **Secret key**
3. For webhooks:
   - Go to [Webhooks](https://dashboard.stripe.com/webhooks)
   - Click "Add endpoint"
   - Enter: `https://yourdomain.com/api/webhook`
   - Select events: `payment_intent.succeeded`, `payment_intent.payment_failed`
   - Copy the **Signing secret**

**Test Mode:**
- Use test keys (starting with `sk_test_` and `pk_test_`)
- Test card: `4242 4242 4242 4242`
- Any future expiry date, any CVC

### Binance API Keys

1. Go to [Binance API Management](https://www.binance.com/en/my/settings/api-management)
2. Create new API key
3. **Important:** Enable these permissions:
   - ✅ Enable Spot & Margin Trading
   - ✅ Enable Withdrawals
4. Add IP restriction for security (optional but recommended)
5. Copy **API Key** and **Secret Key**

**⚠️ Security Note:** Never share your API keys or commit them to Git!

### USDT Wallet Address

Get a USDT wallet address from:
- **TrustWallet** (recommended for TRC20)
- **MetaMask** (for ERC20/BEP20)
- **Binance** (your own Binance USDT address)

**Network Options:**
- `TRC20` (Tron) - Lowest fees (~$1)
- `BEP20` (BSC) - Low fees (~$0.50)
- `ERC20` (Ethereum) - High fees (~$15+)
- `POLYGON` - Very low fees (~$0.10)

## 📱 Usage

### Admin Dashboard

1. Open `http://localhost:3000/admin`
2. Generate payment link:
   - Enter amount (e.g., $100)
   - Add customer email (optional)
   - Click "Generate Payment Link"
3. Share the link with your customer

### Customer Payment

1. Customer opens payment link
2. Enters card details
3. Completes payment
4. Receives confirmation

### Automatic Conversion

After payment succeeds:
1. ✅ Stripe processes card payment
2. 💱 System buys USDT on Binance
3. 📤 USDT sent to your wallet
4. 📧 Customer receives email confirmation

## 📊 API Endpoints

### Payment Management

```http
POST /api/create-payment-link
Content-Type: application/json

{
  "amount": 100.00,
  "currency": "USD",
  "customer_email": "customer@example.com",
  "customer_name": "John Doe"
}
```

```http
POST /api/create-payment-intent
Content-Type: application/json

{
  "payment_link_id": "uuid-here"
}
```

```http
GET /api/payments
Query params: ?status=completed&limit=50
```

```http
GET /api/payment/:payment_id/conversion
Returns conversion summary and transaction logs
```

```http
POST /api/payment/:payment_id/retry-conversion
Manually retry USDT conversion for failed payments
```

### Webhooks

```http
POST /api/webhook
Stripe webhook endpoint (configured in Stripe Dashboard)
```

## 💰 Fee Structure

| Service | Fee | Example ($100) |
|---------|-----|----------------|
| Stripe | 2.9% + $0.30 | $3.20 |
| Binance Trading | 0.1% | $0.10 |
| Network Fee (TRC20) | ~$1 | $1.00 |
| **Total Fees** | **~4.3%** | **$4.30** |
| **You Receive** | | **~95.70 USDT** |

*Fees vary by network and Binance VIP level*

## 🗂️ Project Structure

```
payment-gateway/
├── backend/
│   ├── routes/
│   │   ├── payment.js          # Payment API routes
│   │   └── webhook.js          # Stripe webhook handler
│   ├── services/
│   │   ├── database.js         # SQLite database service
│   │   ├── stripe.js           # Stripe integration
│   │   ├── binance.js          # Binance exchange service
│   │   └── usdtConversion.js   # USDT conversion logic
│   ├── config/
│   │   └── payments.db         # SQLite database (auto-created)
│   └── server.js               # Express server
├── frontend/
│   ├── index.html              # Home page
│   ├── pay.html                # Payment page
│   ├── success.html            # Success page
│   ├── error.html              # Error page
│   └── admin.html              # Admin dashboard
├── public/
│   ├── css/
│   │   ├── payment.css         # Payment page styles
│   │   └── admin.css           # Admin dashboard styles
│   └── js/
│       ├── payment.js          # Payment page logic
│       └── admin.js            # Admin dashboard logic
├── .env                        # Environment configuration
├── .env.example                # Example configuration
├── package.json                # Dependencies
└── README.md                   # This file
```

## 🔒 Security

### Current Implementation

- ✅ Environment variables for sensitive data
- ✅ Stripe webhook signature verification
- ✅ HTTPS required for production
- ✅ Input validation on amounts
- ✅ Database transaction logging

### Recommended for Production

- [ ] Add authentication to admin dashboard
- [ ] Implement rate limiting
- [ ] Add CSRF protection
- [ ] Enable CORS restrictions
- [ ] Use SSL/TLS certificates
- [ ] Implement IP whitelisting for API
- [ ] Add 2FA for admin access
- [ ] Regular security audits

## 🧪 Testing

### Test Payment Flow

1. Start server: `npm start`
2. Open admin: `http://localhost:3000/admin`
3. Generate payment link with amount: $10
4. Open payment link
5. Use Stripe test card:
   - Card: `4242 4242 4242 4242`
   - Expiry: Any future date
   - CVC: Any 3 digits
   - ZIP: Any 5 digits
6. Complete payment
7. Check admin dashboard for status
8. View console logs for USDT conversion

### Test Mode

- System works in **simulation mode** if Binance API not configured
- Simulates USDT purchase and withdrawal
- Perfect for testing payment flow
- No actual crypto transactions

## 🚀 Deployment

### Option 1: VPS (DigitalOcean, AWS, etc.)

```bash
# Install Node.js
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt-get install -y nodejs

# Clone project
git clone https://github.com/yourusername/payment-gateway.git
cd payment-gateway

# Install dependencies
npm install

# Configure environment
nano .env

# Install PM2 for process management
npm install -g pm2

# Start with PM2
pm2 start backend/server.js --name payment-gateway
pm2 save
pm2 startup
```

### Option 2: Heroku

```bash
# Install Heroku CLI
npm install -g heroku

# Login and create app
heroku login
heroku create your-payment-gateway

# Set environment variables
heroku config:set STRIPE_SECRET_KEY=your_key
heroku config:set STRIPE_PUBLISHABLE_KEY=your_key
# ... set all other variables

# Deploy
git push heroku main
```

### SSL Certificate

**Required for production!** Stripe webhooks require HTTPS.

**Free SSL with Let's Encrypt:**
```bash
sudo apt install certbot
sudo certbot certonly --standalone -d yourdomain.com
```

**Or use services:**
- Cloudflare (free SSL + CDN)
- AWS Certificate Manager (free with AWS)
- Heroku SSL (automatic with paid dynos)

## 🛠️ Troubleshooting

### Binance API not working

```
⚠️ Binance API not configured. USDT conversion will be simulated.
```

**Solution:** Add valid Binance API keys to `.env` file

### Webhook not receiving events

**Solution:**
1. Expose local server: Use [ngrok](https://ngrok.com)
   ```bash
   ngrok http 3000
   ```
2. Update Stripe webhook URL to ngrok URL
3. Update `.env` STRIPE_WEBHOOK_SECRET

### Payment stuck in "processing"

**Solution:**
1. Check server logs for errors
2. Manually retry: `POST /api/payment/:id/retry-conversion`
3. Check Binance API permissions

### Database errors

**Solution:**
```bash
# Delete and recreate database
rm backend/config/payments.db
# Restart server (will auto-create)
npm start
```

## 📝 Customization

### Change Payment Page Design

Edit `public/css/payment.css` and `frontend/pay.html`

### Add Your Logo

Replace the SVG logo in:
- `frontend/pay.html` (line 13)
- `frontend/admin.html` (line 17)

### Change Colors

Main colors in CSS files:
- Primary: `#667eea`
- Secondary: `#764ba2`
- Success: `#10b981`
- Error: `#ef4444`

## 📜 License

MIT License - feel free to use for personal or commercial projects

## 🤝 Support

For issues or questions:
- Create an issue on GitHub
- Email: support@yourcompany.com

## 🎉 Credits

Built with:
- [Stripe](https://stripe.com) - Payment processing
- [Binance API](https://www.binance.com/en/binance-api) - Crypto exchange
- [Express.js](https://expressjs.com) - Backend framework
- [SQLite](https://www.sqlite.org) - Database

---

**⚠️ Disclaimer:** This is a payment processing system handling real money and cryptocurrency. Always test thoroughly before deploying to production. Ensure compliance with local regulations regarding payment processing and cryptocurrency trading.
