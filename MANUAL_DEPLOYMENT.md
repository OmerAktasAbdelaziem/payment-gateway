# Manual Deployment Steps for Payment Gateway

## Step 1: Make GitHub Repository Public (Temporary)
1. Go to: https://github.com/OmerAktasAbdelaziem/payment-gateway
2. Click "Settings"
3. Scroll to bottom → "Danger Zone"
4. Click "Change visibility" → "Make public"
5. Confirm the change

## Step 2: Connect to Your Server
Open PowerShell and run:
```powershell
ssh -p 65002 u402548537@213.130.145.169
```
Password: `JustOmer2024$`

## Step 3: Navigate and Clone Repository
```bash
cd domains/internationalitpro.com/public_html/gateway
rm -f default.php default.php.old.php
git clone https://github.com/OmerAktasAbdelaziem/payment-gateway.git .
```

## Step 4: Install Node.js (if not installed)
Check if Node.js is installed:
```bash
node --version
npm --version
```

If not installed, install Node.js:
```bash
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt-get install -y nodejs
```

## Step 5: Install Dependencies
```bash
npm install --production
```

## Step 6: Create .env File
```bash
nano .env
```

Paste this configuration (press Ctrl+Shift+V):
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

Save and exit: `Ctrl+X`, then `Y`, then `Enter`

## Step 7: Install and Setup PM2
```bash
npm install -g pm2
pm2 start backend/server.js --name payment-gateway
pm2 save
pm2 startup
```

Copy and run the command that PM2 gives you (it will look like):
```bash
sudo env PATH=$PATH:/usr/bin /usr/lib/node_modules/pm2/bin/pm2 startup systemd -u u402548537 --hp /home/u402548537
```

## Step 8: Check if Application is Running
```bash
pm2 list
pm2 logs payment-gateway
```

You should see the server running on port 3000.

## Step 9: Setup Nginx Reverse Proxy

Check if Nginx config directory exists:
```bash
ls -la /etc/nginx/sites-available/
```

If it doesn't exist, you might need to create a config in the main nginx.conf or use cPanel/Plesk interface.

### Option A: Using cPanel (Recommended for shared hosting)
1. Log into cPanel at your hosting provider
2. Find "Apache Configuration" or "Proxy" settings
3. Add a proxy rule:
   - Path: `/gateway`
   - Proxy to: `http://localhost:3000`

### Option B: Manual Nginx Configuration (if you have access)
```bash
sudo nano /etc/nginx/sites-available/payment-gateway
```

Add:
```nginx
server {
    listen 80;
    server_name internationalitpro.com www.internationalitpro.com;

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

Enable and reload:
```bash
sudo ln -s /etc/nginx/sites-available/payment-gateway /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

## Step 10: Test the Application

Open your browser and visit:
- https://internationalitpro.com/gateway/login

Login with:
- Username: `admin`
- Password: `Admin@2025`

## Step 11: Make Repository Private Again
1. Go to: https://github.com/OmerAktasAbdelaziem/payment-gateway/settings
2. Scroll to bottom → "Danger Zone"
3. Click "Change visibility" → "Make private"

## Troubleshooting

### Check PM2 Logs
```bash
pm2 logs payment-gateway
```

### Restart Application
```bash
pm2 restart payment-gateway
```

### Check if Port 3000 is in Use
```bash
netstat -tulpn | grep 3000
```

### Database Issues
```bash
cd domains/internationalitpro.com/public_html/gateway
ls -la backend/config/
chmod 755 backend/config
chmod 644 backend/config/payments.db
```

## Future Updates

To update the application:
```bash
cd domains/internationalitpro.com/public_html/gateway
git pull origin main
npm install --production
pm2 restart payment-gateway
```

## Important Notes

1. **Stripe Webhook**: After deployment, configure webhook in Stripe dashboard:
   - URL: `https://internationalitpro.com/gateway/api/webhook`
   - Events: `payment_intent.succeeded`, `payment_intent.payment_failed`

2. **SSL Certificate**: Ensure your domain has SSL configured through cPanel or Let's Encrypt

3. **Firewall**: Make sure port 3000 is accessible locally (not publicly, only through Nginx proxy)

4. **Backup**: Regularly backup the database file at `backend/config/payments.db`
