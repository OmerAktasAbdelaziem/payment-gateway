# Deployment Instructions

## Server Setup Steps

### 1. Connect to Server
```bash
ssh -p 65002 u402548537@213.130.145.169
```

### 2. Navigate to deployment directory
```bash
cd domains/internationalitpro.com/public_html/gateway
```

### 3. Clone the repository
```bash
git clone https://github.com/OmerAktasAbdelaziem/payment-gateway.git .
```

### 4. Install Node.js dependencies
```bash
npm install
```

### 5. Create .env file
```bash
nano .env
```

Paste the following configuration:
```env
# Stripe API Keys (LIVE MODE)
STRIPE_SECRET_KEY=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
STRIPE_PUBLISHABLE_KEY=pk_live_51SLloUHqjjklN91QyaOQqGaVfcr0VZBRc5JDY8KhpU2BYEZn7FHhGHjojcN9BfWLCMNigRFrXQcOSJwHgRvhBEMI00YjU7fzAl

# Stripe Webhook Secret
STRIPE_WEBHOOK_SECRET=whsec_your_webhook_secret_here

# Server Configuration
PORT=3000
NODE_ENV=production

# Exchange API Configuration (Binance)
EXCHANGE_API_KEY=mDEkHvI7RR2Yjipem9COGpTMhLqOcdRv4wfzjOO08nSR1AbM5w2mB6QF3fCoeJPT
EXCHANGE_API_SECRET=HYvHUhQ1EW0bKAJXiyT9uGLd3JUQdKvVhNL3Jvb2Lu2fYpg6y1bnFYDXPxemzGPA
EXCHANGE_TYPE=binance

# USDT Wallet Configuration
USDT_WALLET_ADDRESS=TPgGspfthoVxUsEwTusfh7iK5ts7UXfSjk
USDT_NETWORK=TRC20

# Payment Configuration
BASE_URL=https://internationalitpro.com/gateway
PAYMENT_SUCCESS_URL=https://internationalitpro.com/gateway/success.html
PAYMENT_ERROR_URL=https://internationalitpro.com/gateway/error.html

# Database Configuration
DATABASE_PATH=./backend/config/payments.db

# Session Secret
SESSION_SECRET=international-pro-payment-gateway-secret-2025-production
```

### 6. Setup PM2 for process management
```bash
npm install -g pm2
pm2 start backend/server.js --name payment-gateway
pm2 save
pm2 startup
```

### 7. Setup Nginx reverse proxy
Create nginx configuration:
```bash
sudo nano /etc/nginx/sites-available/payment-gateway
```

Add this configuration:
```nginx
server {
    listen 80;
    server_name internationalitpro.com;

    location /gateway {
        proxy_pass http://localhost:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_cache_bypass $http_upgrade;
    }
}
```

Enable the site:
```bash
sudo ln -s /etc/nginx/sites-available/payment-gateway /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 8. Setup SSL with Let's Encrypt
```bash
sudo certbot --nginx -d internationalitpro.com
```

### 9. Verify deployment
Visit: https://internationalitpro.com/gateway/login

Login credentials:
- Username: admin
- Password: Admin@2025

## Useful PM2 Commands

```bash
pm2 list                    # List all processes
pm2 logs payment-gateway    # View logs
pm2 restart payment-gateway # Restart the app
pm2 stop payment-gateway    # Stop the app
pm2 delete payment-gateway  # Remove from PM2
```

## Update Deployment

```bash
cd domains/internationalitpro.com/public_html/gateway
git pull origin main
npm install
pm2 restart payment-gateway
```
