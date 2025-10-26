# PowerShell Deployment Script for Payment Gateway

$SERVER_USER = "u402548537"
$SERVER_HOST = "213.130.145.169"
$SERVER_PORT = "65002"
$SERVER_PASS = "JustOmer2024$"
$DEPLOY_PATH = "domains/internationalitpro.com/public_html/gateway"

Write-Host "🚀 Starting deployment to production server..." -ForegroundColor Cyan

# Create deployment commands script
$deployCommands = @"
cd $DEPLOY_PATH
echo '📂 Current directory:'
pwd
echo ''
echo '🔄 Pulling latest changes from GitHub...'
git pull origin main
echo ''
echo '📦 Installing dependencies...'
npm install --production
echo ''
echo '🔄 Managing PM2 process...'
if pm2 describe payment-gateway > /dev/null 2>&1; then
    echo '   Restarting existing process...'
    pm2 restart payment-gateway
else
    echo '   Starting new process...'
    pm2 start backend/server.js --name payment-gateway
    pm2 save
fi
echo ''
echo '📊 Application status:'
pm2 list
echo ''
echo '✅ Deployment completed successfully!'
echo ''
echo '🌐 Access your application at:'
echo '   https://internationalitpro.com/gateway'
"@

# Save commands to temp file
$tempFile = [System.IO.Path]::GetTempFileName()
$deployCommands | Out-File -FilePath $tempFile -Encoding UTF8

# Execute SSH commands
Write-Host "📡 Connecting to server..." -ForegroundColor Yellow

try {
    # Using plink (PuTTY's SSH client) if available, otherwise use ssh
    if (Get-Command plink -ErrorAction SilentlyContinue) {
        echo y | plink -P $SERVER_PORT -pw $SERVER_PASS "$SERVER_USER@$SERVER_HOST" "bash -s" < $tempFile
    } else {
        Get-Content $tempFile | ssh -p $SERVER_PORT "$SERVER_USER@$SERVER_HOST" "bash -s"
    }
    
    Write-Host "`n✅ Deployment completed successfully!" -ForegroundColor Green
    Write-Host "🌐 Visit: https://internationalitpro.com/gateway" -ForegroundColor Green
} catch {
    Write-Host "`n❌ Deployment failed: $_" -ForegroundColor Red
    exit 1
} finally {
    # Clean up temp file
    Remove-Item $tempFile -ErrorAction SilentlyContinue
}
