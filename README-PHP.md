# Payment Gateway - PHP Version

## Migration to Pure PHP

This payment gateway has been converted from Node.js to **pure PHP** for better compatibility with shared hosting environments.

## Benefits of PHP Version

- ✅ **No PM2 required** - PHP runs per-request
- ✅ **No port management** - Works directly through Apache/LiteSpeed
- ✅ **Simpler deployment** - Just upload files
- ✅ **Native MySQL support** - Built-in PDO
- ✅ **Automatic restarts** - No daemon crashes
- ✅ **Lower resource usage** - No always-running process

## Prerequisites

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Composer (for Stripe SDK)
- mod_rewrite enabled

## Installation

### 1. Install Dependencies

```bash
composer install
```

This will install the Stripe PHP SDK.

### 2. Configure Environment

Copy `.env.example` to `.env` and configure:

```bash
cp .env.example .env
```

Edit `.env`:
```
# Database
DB_HOST=localhost
DB_PORT=3306
DB_NAME=u402548537_gateway
DB_USER=u402548537_root
DB_PASSWORD=your_password

# Stripe Keys
STRIPE_SECRET_KEY=sk_live_xxx
STRIPE_PUBLISHABLE_KEY=pk_live_xxx
STRIPE_WEBHOOK_SECRET=whsec_xxx

# URLs
BASE_URL=https://internationalitpro.com
PAYMENT_SUCCESS_URL=https://internationalitpro.com/success.html
PAYMENT_ERROR_URL=https://internationalitpro.com/error.html
```

### 3. Setup .htaccess

```bash
cp .htaccess-php .htaccess
```

### 4. Create Database Tables

The database tables should already exist from the Node.js version. If not, run:

```sql
-- Users table (if not exists)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Payments table (should already exist)
-- No changes needed
```

## File Structure

```
payment-gateway/
├── php-backend/          # PHP Backend
│   ├── config.php        # Configuration & environment
│   ├── Database.php      # Database connection class
│   ├── Auth.php          # Authentication class
│   ├── Payment.php       # Payment management class
│   ├── StripeService.php # Stripe integration class
│   └── api.php           # API router
├── frontend/             # Frontend files (HTML/CSS/JS)
│   ├── index.html
│   ├── login.html
│   ├── admin.html
│   ├── payment.html
│   ├── success.html
│   ├── error.html
│   ├── css/
│   ├── js/
│   └── images/
├── vendor/               # Composer dependencies
├── .htaccess             # Apache/LiteSpeed routing
├── .env                  # Environment variables
└── composer.json         # PHP dependencies
```

## API Endpoints

All endpoints are prefixed with `/api/`

### Authentication
- `POST /api/auth/login` - Login
- `POST /api/auth/logout` - Logout
- `GET /api/auth/check` - Check auth status

### Payments (require authentication)
- `GET /api/payments` - Get all payments
- `POST /api/payments` - Create payment
- `GET /api/payments/{id}` - Get payment by ID
- `PATCH /api/payments/{id}` - Update payment
- `DELETE /api/payments/{id}` - Delete payment
- `GET /api/payments/stats` - Get statistics

### Stripe
- `POST /api/stripe/create-intent/{payment_id}` - Create payment intent
- `GET /api/stripe/config` - Get public key
- `POST /api/stripe/webhook` - Stripe webhook handler

### Public
- `GET /api/public/payment/{id}` - Get public payment info
- `GET /api/health` - Health check

## Deployment to Server

### Option 1: Manual Upload

1. **Upload files via FTP/SFTP:**
```bash
# Upload all files to public_html
scp -P 65002 -r * u402548537@213.130.145.169:domains/internationalitpro.com/public_html/
```

2. **Install Composer dependencies on server:**
```bash
ssh -p 65002 u402548537@213.130.145.169
cd domains/internationalitpro.com/public_html
composer install --no-dev --optimize-autoloader
```

3. **Set permissions:**
```bash
chmod 755 php-backend/
chmod 644 php-backend/*.php
chmod 644 .htaccess
```

### Option 2: Git Deploy

```bash
# On server
cd domains/internationalitpro.com/public_html
git pull origin main
composer install --no-dev
```

## Testing

### Test API Health
```bash
curl https://internationalitpro.com/api/health
```

### Test Login
```bash
curl -X POST https://internationalitpro.com/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"gateway","password":"Gateway2024$"}'
```

### Test Payment Creation
```bash
curl -X POST https://internationalitpro.com/api/payments \
  -H "Content-Type: application/json" \
  -b "gateway_session=YOUR_SESSION_COOKIE" \
  -d '{
    "amount": 100,
    "currency": "USD",
    "description": "Test Payment"
  }'
```

## Troubleshooting

### 500 Internal Server Error

Check PHP error log:
```bash
tail -f ~/domains/internationalitpro.com/public_html/error_log
```

### Database Connection Failed

Verify credentials in `.env` file and test connection:
```bash
php -r "new PDO('mysql:host=localhost;dbname=u402548537_gateway', 'u402548537_root', 'PASSWORD');"
```

### Composer Not Found

Install composer:
```bash
curl -sS https://getcomposer.org/installer | php
php composer.phar install
```

## Stripe Webhook Setup

1. Go to Stripe Dashboard → Webhooks
2. Add endpoint: `https://internationalitpro.com/api/stripe/webhook`
3. Select events:
   - `payment_intent.succeeded`
   - `payment_intent.payment_failed`
   - `payment_intent.canceled`
4. Copy webhook secret to `.env`

## Differences from Node.js Version

| Feature | Node.js | PHP |
|---------|---------|-----|
| Process Manager | PM2 | Not needed |
| Port | 3000 (proxied) | Direct (80/443) |
| Sessions | express-session | PHP sessions |
| Database | mysql2 | PDO |
| Stripe | stripe npm | stripe/stripe-php |
| Routing | Express | .htaccess + api.php |
| Auto-restart | CRON script | Built-in |

## Maintenance

No CRON jobs needed! PHP handles everything automatically.

Optional: Setup log rotation for error_log file.

## Support

For issues, check:
1. PHP error log: `~/domains/internationalitpro.com/public_html/error_log`
2. Apache error log: `~/domains/internationalitpro.com/logs/error_log`
3. Stripe dashboard for webhook errors

## Login Credentials

- Username: `gateway`
- Password: `Gateway2024$`

## Access URLs

- Admin: https://internationalitpro.com/login
- Dashboard: https://internationalitpro.com/admin
- API: https://internationalitpro.com/api/health
- Payment: https://internationalitpro.com/pay/{PAYMENT_ID}
