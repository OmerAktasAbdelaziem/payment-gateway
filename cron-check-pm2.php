#!/usr/bin/env php
<?php
/**
 * PM2 Health Check and Auto-Restart Script
 * Add to crontab to run every 5 minutes:
 * */5 * * * * /usr/bin/php /home/u402548537/domains/internationalitpro.com/public_html/gateway/cron-check-pm2.php >> /home/u402548537/domains/internationalitpro.com/public_html/gateway/logs/cron.log 2>&1
 */

date_default_timezone_set('UTC');

// Configuration
$pm2Path = '/home/u402548537/.nvm/versions/node/v22.21.0/bin/pm2';
$nodePath = '/home/u402548537/.nvm/versions/node/v22.21.0/bin/node';
$appPath = '/home/u402548537/domains/internationalitpro.com/public_html/gateway';
$appName = 'payment-gateway';
$healthUrl = 'http://127.0.0.1:3000/api/health';

// Logging function
function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp] $message\n";
}

logMessage("========== PM2 Health Check Started ==========");

// Check if PM2 process is running
$statusCmd = "$nodePath $pm2Path status $appName 2>&1";
$statusOutput = shell_exec($statusCmd);

logMessage("Checking PM2 status...");

// Check if the app is in 'online' state
if (strpos($statusOutput, 'online') !== false) {
    logMessage("✅ PM2 status: ONLINE");
    
    // Double-check with health endpoint
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $healthUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        if (isset($data['status']) && $data['status'] === 'ok') {
            logMessage("✅ Health check: PASSED");
            logMessage("Application is healthy. No action needed.");
        } else {
            logMessage("⚠️ Health check: FAILED (Invalid response)");
            logMessage("Restarting PM2 process...");
            restartPm2();
        }
    } else {
        logMessage("⚠️ Health check: FAILED (HTTP $httpCode)");
        logMessage("Restarting PM2 process...");
        restartPm2();
    }
} elseif (strpos($statusOutput, 'errored') !== false || strpos($statusOutput, 'stopped') !== false) {
    logMessage("❌ PM2 status: ERRORED/STOPPED");
    logMessage("Restarting PM2 process...");
    restartPm2();
} else {
    logMessage("❌ PM2 status: NOT FOUND");
    logMessage("Starting PM2 process...");
    startPm2();
}

logMessage("========== PM2 Health Check Completed ==========\n");

/**
 * Restart PM2 process
 */
function restartPm2() {
    global $nodePath, $pm2Path, $appName;
    
    $restartCmd = "$nodePath $pm2Path restart $appName 2>&1";
    $restartOutput = shell_exec($restartCmd);
    
    logMessage("Restart output:");
    logMessage($restartOutput);
    
    // Verify restart
    sleep(2);
    $statusCmd = "$nodePath $pm2Path status $appName 2>&1";
    $statusOutput = shell_exec($statusCmd);
    
    if (strpos($statusOutput, 'online') !== false) {
        logMessage("✅ Restart successful - App is now ONLINE");
        sendAlert("PM2 Process Restarted", "Payment gateway was restarted automatically by CRON at " . date('Y-m-d H:i:s'));
    } else {
        logMessage("❌ Restart failed - App is still DOWN");
        sendAlert("PM2 Restart Failed", "Failed to restart payment gateway at " . date('Y-m-d H:i:s'));
    }
}

/**
 * Start PM2 process (if completely stopped)
 */
function startPm2() {
    global $nodePath, $pm2Path, $appPath, $appName;
    
    // Change to app directory and start using ecosystem config
    $startCmd = "cd $appPath && $nodePath $pm2Path start ecosystem.config.js 2>&1";
    $startOutput = shell_exec($startCmd);
    
    logMessage("Start output:");
    logMessage($startOutput);
    
    // Verify start
    sleep(2);
    $statusCmd = "$nodePath $pm2Path status $appName 2>&1";
    $statusOutput = shell_exec($statusCmd);
    
    if (strpos($statusOutput, 'online') !== false) {
        logMessage("✅ Start successful - App is now ONLINE");
        sendAlert("PM2 Process Started", "Payment gateway was started automatically by CRON at " . date('Y-m-d H:i:s'));
    } else {
        logMessage("❌ Start failed - App is still DOWN");
        sendAlert("PM2 Start Failed", "Failed to start payment gateway at " . date('Y-m-d H:i:s'));
    }
}

/**
 * Send alert (can be extended to email, SMS, webhook, etc.)
 */
function sendAlert($subject, $message) {
    // Log to file
    $alertFile = '/home/u402548537/domains/internationalitpro.com/public_html/gateway/logs/alerts.log';
    $alertDir = dirname($alertFile);
    
    if (!is_dir($alertDir)) {
        mkdir($alertDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($alertFile, "[$timestamp] $subject: $message\n", FILE_APPEND);
    
    logMessage("🔔 Alert logged: $subject");
    
    // Optional: Send email (uncomment and configure if needed)
    // mail('your-email@example.com', $subject, $message);
    
    // Optional: Send to webhook (uncomment and configure if needed)
    /*
    $webhookUrl = 'https://your-webhook-url.com/notify';
    $payload = json_encode([
        'subject' => $subject,
        'message' => $message,
        'timestamp' => $timestamp
    ]);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $webhookUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
    */
}
?>
