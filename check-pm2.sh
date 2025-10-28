#!/bin/bash
# PM2 Health Check Script for CRON
# Add to crontab: */5 * * * * /home/u402548537/domains/internationalitpro.com/public_html/gateway/check-pm2.sh >> /home/u402548537/domains/internationalitpro.com/public_html/gateway/logs/cron.log 2>&1

# Configuration
PM2_PATH="$HOME/.nvm/versions/node/v22.21.0/bin/pm2"
NODE_PATH="$HOME/.nvm/versions/node/v22.21.0/bin/node"
APP_PATH="$HOME/domains/internationalitpro.com/public_html/gateway"
APP_NAME="payment-gateway"
HEALTH_URL="http://127.0.0.1:3000/api/health"

echo "========================================="
echo "PM2 Health Check - $(date)"
echo "========================================="

# Check PM2 status
STATUS=$($NODE_PATH $PM2_PATH status $APP_NAME 2>&1)

if echo "$STATUS" | grep -q "online"; then
    echo "✅ PM2 Status: ONLINE"
    
    # Check health endpoint
    HEALTH=$(curl -s -o /dev/null -w "%{http_code}" --connect-timeout 3 --max-time 5 $HEALTH_URL)
    
    if [ "$HEALTH" == "200" ]; then
        echo "✅ Health Check: PASSED"
        echo "Application is healthy. No action needed."
    else
        echo "❌ Health Check: FAILED (HTTP $HEALTH)"
        echo "Restarting PM2..."
        cd $APP_PATH
        $NODE_PATH $PM2_PATH restart $APP_NAME
        echo "Restart command executed."
    fi
    
elif echo "$STATUS" | grep -q "errored\|stopped"; then
    echo "❌ PM2 Status: ERRORED/STOPPED"
    echo "Restarting PM2..."
    cd $APP_PATH
    $NODE_PATH $PM2_PATH restart $APP_NAME
    echo "Restart command executed."
    
else
    echo "❌ PM2 Status: NOT FOUND"
    echo "Starting PM2..."
    cd $APP_PATH
    $NODE_PATH $PM2_PATH start ecosystem.config.js
    echo "Start command executed."
fi

echo ""
