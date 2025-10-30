<?php
// Test database connection
echo "Testing database connection...\n\n";

$configs = [
    ['user' => 'u402548537_gateway', 'db' => 'u402548537_v4xns'],
    ['user' => 'u402548537_v4xns', 'db' => 'u402548537_v4xns'],
    ['user' => 'u402548537_root', 'db' => 'u402548537_v4xns'],
];

$password = 'JustOmer2024$';
$host = '127.0.0.1';

foreach ($configs as $config) {
    echo "Trying: User={$config['user']}, DB={$config['db']}\n";
    try {
        $pdo = new PDO(
            "mysql:host=$host;dbname={$config['db']}", 
            $config['user'], 
            $password
        );
        echo "✅ SUCCESS! Connected with:\n";
        echo "   User: {$config['user']}\n";
        echo "   Database: {$config['db']}\n\n";
        
        // Test query
        $stmt = $pdo->query("SELECT DATABASE() as db, USER() as user");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "   Current Database: {$result['db']}\n";
        echo "   Current User: {$result['user']}\n\n";
        exit(0);
    } catch (Exception $e) {
        echo "❌ Failed: " . $e->getMessage() . "\n\n";
    }
}

echo "❌ All connection attempts failed!\n";
exit(1);
