# 🔧 Database Setup Instructions

## ⚠️ Action Required: Grant Database Permissions in cPanel

The new database `u402548537_v4xns` has been created, but the user doesn't have access yet.

### 📋 Steps to Fix:

#### 1. **Login to cPanel**
   - URL: https://hpanel.hostinger.com
   - Login with your Hostinger credentials

#### 2. **Add User to Database**
   Go to: **Databases → MySQL Databases**

   Find the section: **Add User To Database**

   - **User**: Select `u402548537_gateway` (or create new user)
   - **Database**: Select `u402548537_v4xns`
   - **Click**: "Add"

#### 3. **Grant ALL PRIVILEGES**
   After adding the user, you'll see a permissions page:
   
   ✅ Check "ALL PRIVILEGES" checkbox
   
   Click "Make Changes"

#### 4. **Verify Database User**
   
   Option A: Use the existing user
   ```
   User: u402548537_gateway
   Password: JustOmer2024$
   ```

   Option B: Create a new user (recommended)
   ```
   User: u402548537_v4xns
   Password: JustOmer2024$ (or create new)
   ```

#### 5. **Update .env File (if using new user)**
   
   SSH into server and edit:
   ```bash
   ssh -p 65002 u402548537@213.130.145.169
   cd domains/internationalitpro.com/public_html
   nano .env
   ```
   
   Update these lines:
   ```
   DB_NAME=u402548537_v4xns
   DB_USER=u402548537_v4xns    # or u402548537_gateway
   DB_PASSWORD=YourPassword
   ```

#### 6. **Run Migration After Fixing**
   
   Once permissions are granted:
   ```bash
   ssh -p 65002 u402548537@213.130.145.169
   cd domains/internationalitpro.com/public_html
   php migrate.php
   php seed.php
   ```

---

## 🎯 Quick Alternative: Use Existing Database

If you want to keep using the old database `u402548537_gateway`, you can revert:

```bash
ssh -p 65002 u402548537@213.130.145.169
cd domains/internationalitpro.com/public_html
sed -i 's/DB_NAME=u402548537_v4xns/DB_NAME=u402548537_gateway/' .env
php migrate.php
php seed.php
```

This will recreate tables in the existing database.

---

## 📝 What the Scripts Will Do:

### migrate.php:
- ✅ Drops old tables (users, payments)
- ✅ Creates fresh tables with proper structure
- ✅ Shows table structure

### seed.php:
- ✅ Creates admin user: `gateway / Gateway2024$`
- ✅ Adds 5 sample payments for testing
- ✅ Shows summary statistics

---

## 🆘 Need Help?

If you're having trouble with cPanel, here's what we need:

1. **Correct database user** (with access to `u402548537_v4xns`)
2. **Database password**

Then I can update the .env file and run migrations remotely.

---

## ✅ After Setup Complete:

Test the payment gateway:
- 🔗 https://internationalitpro.com/login
- 👤 Username: `gateway`
- 🔐 Password: `Gateway2024$`

All your payment data will be fresh and clean!
