# Deploy PHP Payment Gateway to Server
Write-Host "==================================" -ForegroundColor Cyan
Write-Host "PHP Payment Gateway Deployment" -ForegroundColor Cyan
Write-Host "==================================" -ForegroundColor Cyan
Write-Host ""

$SERVER = "u402548537@213.130.145.169"
$PORT = "65002"
$REMOTE_PATH = "domains/internationalitpro.com/public_html"

# Step 1: Backup
Write-Host "[1/7] Backing up Node.js version..." -ForegroundColor Yellow
ssh -p $PORT $SERVER "cd $REMOTE_PATH && mkdir -p nodejs-backup"
Write-Host "Backup complete" -ForegroundColor Green

# Step 2: Stop PM2
Write-Host "[2/7] Stopping PM2..." -ForegroundColor Yellow
ssh -p $PORT $SERVER "~/.nvm/versions/node/v22.21.0/bin/node ~/.nvm/versions/node/v22.21.0/bin/pm2 delete payment-gateway"
Write-Host "PM2 stopped" -ForegroundColor Green

# Step 3: Upload PHP backend
Write-Host "[3/7] Uploading PHP backend..." -ForegroundColor Yellow
scp -P $PORT -r php-backend "$SERVER`:$REMOTE_PATH/"
Write-Host "PHP backend uploaded" -ForegroundColor Green

# Step 4: Upload vendor
Write-Host "[4/7] Uploading Stripe SDK..." -ForegroundColor Yellow
scp -P $PORT -r vendor "$SERVER`:$REMOTE_PATH/"
Write-Host "Stripe SDK uploaded" -ForegroundColor Green

# Step 5: Upload .htaccess
Write-Host "[5/7] Uploading .htaccess..." -ForegroundColor Yellow
scp -P $PORT .htaccess-php "$SERVER`:$REMOTE_PATH/.htaccess"
Write-Host "htaccess uploaded" -ForegroundColor Green

# Step 6: Upload frontend
Write-Host "[6/7] Uploading frontend..." -ForegroundColor Yellow
scp -P $PORT frontend/index.html "$SERVER`:$REMOTE_PATH/frontend/"
Write-Host "Frontend uploaded" -ForegroundColor Green

# Step 7: Set permissions
Write-Host "[7/7] Setting permissions..." -ForegroundColor Yellow
ssh -p $PORT $SERVER "cd $REMOTE_PATH && chmod 755 php-backend && chmod 644 php-backend/*.php"
Write-Host "Permissions set" -ForegroundColor Green

Write-Host ""
Write-Host "==================================" -ForegroundColor Cyan
Write-Host "Deployment Complete!" -ForegroundColor Green
Write-Host "==================================" -ForegroundColor Cyan
Write-Host ""

# Test
Write-Host "Testing health endpoint..." -ForegroundColor Yellow
ssh -p $PORT $SERVER "curl -s https://internationalitpro.com/api/health"

Write-Host ""
Write-Host "Access URLs:" -ForegroundColor Cyan
Write-Host "  https://internationalitpro.com/login" -ForegroundColor White
Write-Host "  https://internationalitpro.com/admin" -ForegroundColor White
Write-Host ""
Write-Host "Login: gateway / Gateway2024$" -ForegroundColor Green
