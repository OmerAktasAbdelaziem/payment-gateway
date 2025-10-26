#!/bin/bash

# Deployment script for Payment Gateway
# This script automates the deployment to production server

set -e

echo "🚀 Starting deployment to production server..."

# Server details
SERVER_USER="u402548537"
SERVER_HOST="213.130.145.169"
SERVER_PORT="65002"
DEPLOY_PATH="domains/internationalitpro.com/public_html/gateway"

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${YELLOW}📡 Connecting to server...${NC}"

# Execute deployment commands on server
ssh -p $SERVER_PORT $SERVER_USER@$SERVER_HOST << 'ENDSSH'
    set -e
    
    echo "📂 Navigating to deployment directory..."
    cd domains/internationalitpro.com/public_html/gateway
    
    echo "🔄 Pulling latest changes from GitHub..."
    git pull origin main
    
    echo "📦 Installing dependencies..."
    npm install --production
    
    echo "🔄 Restarting application with PM2..."
    if pm2 describe payment-gateway > /dev/null 2>&1; then
        pm2 restart payment-gateway
        echo "✅ Application restarted"
    else
        pm2 start backend/server.js --name payment-gateway
        pm2 save
        echo "✅ Application started"
    fi
    
    echo "📊 Application status:"
    pm2 list
    
    echo "✅ Deployment completed successfully!"
ENDSSH

echo -e "${GREEN}✅ Deployment completed!${NC}"
echo -e "${GREEN}🌐 Visit: https://internationalitpro.com/gateway${NC}"
