# 🚀 cPanel Deployment Guide for Payment Gateway

## ⚠️ Important: Node.js Setup Required

Your hosting is a **shared hosting with cPanel**. Node.js needs to be enabled through cPanel interface.

## Step 1: Enable Node.js in cPanel

### Option A: Using cPanel "Setup Node.js App" (Recommended)

1. **Login to cPanel**
   - Go to your hosting control panel
   - URL usually: `https://internationalitpro.com:2083` or similar

2. **Find "Setup Node.js App"**
   - Search for "Node" in cPanel search bar
   - Or look in "Software" section
   - Click on "Setup Node.js App" or "Node.js Selector"

3. **Create Node.js Application**
   - Click "Create Application"
   - **Node.js version**: Select 18.x or higher
   - **Application mode**: Production
   - **Application root**: `domains/internationalitpro.com/public_html/gateway`
   - **Application URL**: `gateway` or leave as root
   - **Application startup file**: `backend/server.js`
   - **Environment variables**: Click "Add Variable" and add each:
     ```
     STRIPE_SECRET_KEY=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
     STRIPE_PUBLISHABLE_KEY=pk_live_51SLloUHqjjklN91QyaOQqGaVfcr0VZBRc5JDY8KhpU2BYEZn7FHhGHjojcN9BfWLCMNigRFrXQcOSJwHgRvhBEMI00YjU7fzAl
     STRIPE_WEBHOOK_SECRET=whsec_your_webhook_secret_here
     PORT=3000
     NODE_ENV=production
     EXCHANGE_API_KEY=mDEkHvI7RR2Yjipem9COGpTMhLqOcdRv4wfzjOO08nSR1AbM5w2mB6QF3fCoeJPT
     EXCHANGE_API_SECRET=HYvHUhQ1EW0bKAJXiyT9uGLd3JUQdKvVhNL3Jvb2Lu2fYpg6y1bnFYDXPxemzGPA
     EXCHANGE_TYPE=binance
     USDT_WALLET_ADDRESS=TPgGspfthoVxUsEwTusfh7iK5ts7UXfSjk
     USDT_NETWORK=TRC20
     BASE_URL=https://internationalitpro.com/gateway
     PAYMENT_SUCCESS_URL=https://internationalitpro.com/gateway/success.html
     PAYMENT_ERROR_URL=https://internationalitpro.com/gateway/error.html
     DATABASE_PATH=./backend/config/payments.db
     SESSION_SECRET=international-pro-payment-gateway-secret-2025-production
     ```

4. **Click "Create"**
   - cPanel will create the Node.js environment
   - Wait for it to complete

5. **Install Dependencies**
   - After creation, find "Run NPM Install" button
   - Click it to install all dependencies from package.json
   - Wait for completion

6. **Start Application**
   - Click "Start App" or "Restart App"
   - Application should now be running!

### Option B: Contact Hosting Support

If you don't see "Setup Node.js App" in cPanel:

1. **Open Support Ticket** with your hosting provider
2. **Request**: "Please enable Node.js support for my account"
3. **Specify**: Node.js version 18 or higher
4. **Mention**: Need it for application in `domains/internationalitpro.com/public_html/gateway`

## Step 2: Alternative - Create .env File Manually

If you prefer SSH method after Node.js is enabled:

```bash
ssh -p 65002 u402548537@213.130.145.169
cd domains/internationalitpro.com/public_html/gateway
nano .env
```

Paste all the environment variables listed above, save (Ctrl+X, Y, Enter)

Then:
```bash
npm install
npm start
```

## Step 3: Setup Reverse Proxy (if needed)

If cPanel doesn't auto-configure the proxy:

1. **Go to cPanel → "Proxy Applications"** or **"Application Manager"**
2. Set up proxy for your Node.js app:
   - **Domain**: internationalitpro.com
   - **Path**: /gateway
   - **Proxy to**: http://localhost:3000

## Step 4: Access Your Application

After setup is complete:

🌐 **Login URL**: `https://internationalitpro.com/gateway/login`

**Credentials:**
- Username: `admin`
- Password: `Admin@2025`

## Step 5: Setup Stripe Webhook

1. Go to: https://dashboard.stripe.com/webhooks
2. Click "Add endpoint"
3. **Endpoint URL**: `https://internationalitpro.com/gateway/api/webhook`
4. **Events to send**:
   - `payment_intent.succeeded`
   - `payment_intent.payment_failed`
5. Copy the "Signing secret" (starts with `whsec_`)
6. Update in cPanel Node.js App environment variables:
   - Variable: `STRIPE_WEBHOOK_SECRET`
   - Value: (paste the signing secret)
7. Restart the application

## Troubleshooting

### Application Won't Start

1. **Check Logs in cPanel**
   - Go to "Setup Node.js App"
   - Click on your application
   - View "Error Log" or "Output Log"

2. **Check File Permissions**
```bash
ssh -p 65002 u402548537@213.130.145.169
cd domains/internationalitpro.com/public_html/gateway
chmod -R 755 backend
chmod -R 755 frontend
chmod -R 755 public
mkdir -p backend/config
chmod 755 backend/config
```

3. **Verify Package Installation**
   - In cPanel Node.js App interface
   - Click "Run NPM Install" again
   - Watch for any errors

### Database Issues

```bash
cd domains/internationalitpro.com/public_html/gateway
mkdir -p backend/config
touch backend/config/payments.db
chmod 644 backend/config/payments.db
```

### Port Already in Use

- cPanel usually assigns ports automatically
- Check the assigned port in Node.js App settings
- Update PORT variable if needed

## Managing the Application

### Restart Application
- cPanel → "Setup Node.js App" → Click your app → "Restart"

### View Logs
- cPanel → "Setup Node.js App" → Click your app → "Open Logs"

### Update Application
```bash
ssh -p 65002 u402548537@213.130.145.169
cd domains/internationalitpro.com/public_html/gateway
git pull origin main
```
Then in cPanel → Restart the Node.js app

## Important Security Notes

1. **Make Repository Private Again**: After deployment, make your GitHub repository private
2. **Change Default Password**: After first login, create a new admin user with a different password
3. **SSL Certificate**: Ensure SSL is enabled on your domain (should be automatic with cPanel)
4. **Regular Backups**: Backup `backend/config/payments.db` regularly

## Support

If you encounter issues:
1. Check the error logs in cPanel Node.js App interface
2. Contact your hosting support for Node.js specific issues
3. Check if your hosting plan supports Node.js applications

---

## Quick Checklist

- [ ] Node.js enabled in cPanel
- [ ] Application created in "Setup Node.js App"
- [ ] All environment variables added
- [ ] NPM packages installed
- [ ] Application started successfully
- [ ] Can access login page
- [ ] Stripe webhook configured
- [ ] Repository made private again
- [ ] Default password changed
