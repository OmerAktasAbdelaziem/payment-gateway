#!/bin/bash
# Keep-alive script for payment gateway
# Run this with: nohup bash keep-alive.sh &

PM2_PATH="/home/u402548537/.nvm/versions/node/v22.21.0/bin/node /home/u402548537/.nvm/versions/node/v22.21.0/bin/pm2"
APP_DIR="/home/u402548537/domains/internationalitpro.com/public_html/gateway"

while true; do
  cd "$APP_DIR"
  
  # Check if payment-gateway is running
  STATUS=$($PM2_PATH status | grep payment-gateway | grep online)
  
  if [ -z "$STATUS" ]; then
    echo "$(date): Payment gateway is down, restarting..."
    $PM2_PATH start ecosystem.config.js
    $PM2_PATH save
  fi
  
  # Check every 2 minutes
  sleep 120
done
