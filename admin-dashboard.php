<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin-login.php');
    exit;
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /admin-login.php');
    exit;
}

require_once __DIR__ . '/php-backend/config.php';

// Handle status update to completed
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_payment'])) {
    $paymentId = $_POST['payment_id'];
    
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            throw new Exception("Database connection failed");
        }
        
        $stmt = $conn->prepare("UPDATE payment_links SET status = 'completed', updated_at = NOW() WHERE id = ? AND status = 'processing'");
        $stmt->bind_param("s", $paymentId);
        $stmt->execute();
        $stmt->close();
        $conn->close();
        
        $_SESSION['success_message'] = "Payment marked as completed!";
        header('Location: /admin-dashboard.php');
        exit;
        
    } catch (Exception $e) {
        $error = "Failed to update payment: " . $e->getMessage();
    }
}

// Handle payment link creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_link'])) {
    $amount = floatval($_POST['amount']);
    $currency = strtoupper($_POST['currency']);
    $description = trim($_POST['description']);
    $clientName = trim($_POST['client_name']);
    
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            throw new Exception("Database connection failed");
        }
        
        // Generate unique payment link ID
        $paymentId = 'PAY_' . strtoupper(bin2hex(random_bytes(20)));
        
        $stmt = $conn->prepare("INSERT INTO payment_links (id, amount, currency, description, client_name, status, created_at) VALUES (?, ?, ?, ?, ?, 'active', NOW())");
        $stmt->bind_param("sdsss", $paymentId, $amount, $currency, $description, $clientName);
        $stmt->execute();
        $stmt->close();
        $conn->close();
        
        // Store success message in session
        $_SESSION['success_message'] = "Payment link created successfully!";
        $_SESSION['new_payment_id'] = $paymentId;
        
        // Redirect to prevent form resubmission on refresh
        header('Location: /admin-dashboard.php');
        exit;
        
    } catch (Exception $e) {
        $error = "Failed to create payment link: " . $e->getMessage();
    }
}

// Check for success message in session
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    $paymentId = $_SESSION['new_payment_id'];
    $newLinkUrl = BASE_URL . '/pay/' . $paymentId;
    
    // Clear the session message
    unset($_SESSION['success_message']);
    unset($_SESSION['new_payment_id']);
}

// Get filter parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 6;
$offset = ($page - 1) * $perPage;

$filterDate = isset($_GET['date']) ? $_GET['date'] : '';
$filterAmount = isset($_GET['amount']) ? $_GET['amount'] : '';
$filterStatus = isset($_GET['status']) ? $_GET['status'] : '';

// Get payment links with filters
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Database connection failed");
    }
    
    // Build WHERE clause
    $where = [];
    $params = [];
    $types = '';
    
    if (!empty($filterDate)) {
        $where[] = "DATE(created_at) = ?";
        $params[] = $filterDate;
        $types .= 's';
    }
    
    if (!empty($filterAmount)) {
        $where[] = "amount = ?";
        $params[] = floatval($filterAmount);
        $types .= 'd';
    }
    
    if (!empty($filterStatus)) {
        $where[] = "status = ?";
        $params[] = $filterStatus;
        $types .= 's';
    }
    
    $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM payment_links $whereClause";
    if (count($params) > 0) {
        $stmt = $conn->prepare($countSql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $totalRecords = $result->fetch_assoc()['total'];
        $stmt->close();
    } else {
        $result = $conn->query($countSql);
        $totalRecords = $result->fetch_assoc()['total'];
    }
    
    $totalPages = ceil($totalRecords / $perPage);
    
    // Get paginated results
    $sql = "SELECT * FROM payment_links $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    
    if (count($params) > 0) {
        $params[] = $perPage;
        $params[] = $offset;
        $types .= 'ii';
        $stmt->bind_param($types, ...$params);
    } else {
        $stmt->bind_param('ii', $perPage, $offset);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $paymentLinks = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    $paymentLinks = [];
    $totalPages = 0;
    $totalRecords = 0;
    $error = "Failed to load payment links: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Payment Gateway</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f3f4f6;
        }
        
        .header {
            background: linear-gradient(135deg, #df2439 0%, #261444 100%);
            color: white;
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            font-size: 24px;
        }
        
        .header .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 8px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
        }
        
        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .card h2 {
            color: #1f2937;
            margin-bottom: 20px;
            font-size: 20px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr 2fr;
            gap: 15px;
            margin-bottom: 20px;
            align-items: end;
        }
        
        .form-group label {
            display: block;
            color: #374151;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        input, select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s;
        }
        
        input:focus, select:focus {
            outline: none;
            border-color: #df2439;
            box-shadow: 0 0 0 3px rgba(223, 36, 57, 0.1);
        }
        
        .create-btn-wrapper {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }
        
        .create-btn {
            background: linear-gradient(135deg, #df2439 0%, #261444 100%);
            color: white;
            padding: 14px 40px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(223, 36, 57, 0.3);
            transition: all 0.3s;
            min-width: 200px;
        }
        
        .create-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(223, 36, 57, 0.4);
        }
        
        .create-btn:active {
            transform: translateY(0);
        }
        
        .success {
            background: #d1fae5;
            color: #065f46;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .success a {
            color: #065f46;
            font-weight: 600;
        }
        
        .error {
            background: #fee2e2;
            color: #dc2626;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #f9fafb;
            padding: 12px;
            text-align: left;
            color: #6b7280;
            font-weight: 600;
            font-size: 13px;
            border-bottom: 2px solid #e5e7eb;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 14px;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-active {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .badge-processing, .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }
        
        .badge-completed, .badge-success {
            background: #d1fae5;
            color: #065f46;
        }
        
        .badge-inactive {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .copy-btn {
            background: #df2439;
            color: white;
            padding: 4px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .copy-btn:hover {
            background: #c41f2f;
        }
        
        .complete-btn {
            background: #10b981;
            color: white;
            padding: 4px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
        }
        
        .complete-btn:hover {
            background: #059669;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 style="font-size: 24px; font-weight: 700;">INTERNATIONAL PRO</h1>
        <div class="user-info">
            <span>Welcome, <?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
            <a href="?logout=1" class="logout-btn">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <!-- Create Payment Link -->
        <div class="card">
            <h2>Create New Payment Link</h2>
            
            <?php if (isset($success)): ?>
                <div class="success">
                    <?php echo htmlspecialchars($success); ?><br>
                    <strong>Link:</strong> <a href="<?php echo htmlspecialchars($newLinkUrl); ?>" target="_blank"><?php echo htmlspecialchars($newLinkUrl); ?></a>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Client Name *</label>
                        <input type="text" name="client_name" placeholder="John Doe" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Amount *</label>
                        <input type="number" name="amount" step="0.01" min="0.01" placeholder="100.00" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Currency *</label>
                        <select name="currency" required>
                            <option value="USD">USD ($)</option>
                            <option value="EUR">EUR (€)</option>
                            <option value="GBP">GBP (£)</option>
                        </select>
                    </div>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <div class="form-group">
                        <label>Description (Optional)</label>
                        <input type="text" name="description" placeholder="e.g., Payment for service">
                    </div>
                </div>
                
                <div class="create-btn-wrapper">
                    <button type="submit" name="create_link" class="create-btn">
                        ✨ Create Payment Link
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Recent Payment Links -->
        <div class="card">
            <h2>Payment Links (<?php echo $totalRecords; ?> total)</h2>
            
            <!-- Filters -->
            <form method="GET" style="margin-bottom: 20px;">
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 15px;">
                    <div>
                        <input type="date" name="date" value="<?php echo htmlspecialchars($filterDate); ?>" 
                               placeholder="Filter by date" style="padding: 8px;">
                    </div>
                    <div>
                        <input type="number" name="amount" value="<?php echo htmlspecialchars($filterAmount); ?>" 
                               placeholder="Filter by amount" step="0.01" style="padding: 8px;">
                    </div>
                    <div>
                        <select name="status" style="padding: 8px;">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $filterStatus === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="processing" <?php echo $filterStatus === 'processing' ? 'selected' : ''; ?>>Processing</option>
                            <option value="completed" <?php echo $filterStatus === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="inactive" <?php echo $filterStatus === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="expired" <?php echo $filterStatus === 'expired' ? 'selected' : ''; ?>>Expired</option>
                        </select>
                    </div>
                    <div style="display: flex; gap: 5px;">
                        <button type="submit" style="background: #df2439; color: white; border: none; padding: 8px 15px; border-radius: 6px; cursor: pointer; font-size: 13px;">Filter</button>
                        <a href="/admin-dashboard.php" style="background: #6b7280; color: white; border: none; padding: 8px 15px; border-radius: 6px; text-decoration: none; font-size: 13px; display: inline-block;">Clear</a>
                    </div>
                </div>
            </form>
            
            <table>
                <thead>
                    <tr>
                        <th>Payment ID</th>
                        <th>Client Name</th>
                        <th>Amount</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paymentLinks as $link): ?>
                        <tr>
                            <td><code><?php echo htmlspecialchars($link['id']); ?></code></td>
                            <td><strong><?php echo htmlspecialchars($link['client_name']); ?></strong></td>
                            <td><strong><?php echo number_format($link['amount'], 2); ?> <?php echo htmlspecialchars($link['currency']); ?></strong></td>
                            <td><?php echo htmlspecialchars($link['description']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $link['status'] === 'active' ? 'active' : ($link['status'] === 'processing' ? 'warning' : ($link['status'] === 'completed' ? 'success' : 'inactive')); ?>">
                                    <?php echo htmlspecialchars($link['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('Y-m-d H:i', strtotime($link['created_at'])); ?></td>
                            <td>
                                <button class="copy-btn" onclick="copyLink('<?php echo BASE_URL . '/pay/' . htmlspecialchars($link['id']); ?>')">
                                    Copy Link
                                </button>
                                <?php if ($link['status'] === 'processing'): ?>
                                    <form method="POST" style="display: inline; margin-left: 5px;">
                                        <input type="hidden" name="payment_id" value="<?php echo htmlspecialchars($link['id']); ?>">
                                        <button type="submit" name="complete_payment" class="complete-btn" onclick="return confirm('Mark this payment as completed?')">
                                            ✓ Complete
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($paymentLinks)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: #9ca3af;">No payment links found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div style="margin-top: 20px; display: flex; justify-content: center; align-items: center; gap: 10px;">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?><?php echo !empty($filterDate) ? '&date=' . urlencode($filterDate) : ''; ?><?php echo !empty($filterAmount) ? '&amount=' . urlencode($filterAmount) : ''; ?><?php echo !empty($filterStatus) ? '&status=' . urlencode($filterStatus) : ''; ?>" 
                       style="background: #df2439; color: white; padding: 8px 15px; border-radius: 6px; text-decoration: none; font-size: 14px;">← Previous</a>
                <?php endif; ?>
                
                <span style="color: #6b7280; font-size: 14px;">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?><?php echo !empty($filterDate) ? '&date=' . urlencode($filterDate) : ''; ?><?php echo !empty($filterAmount) ? '&amount=' . urlencode($filterAmount) : ''; ?><?php echo !empty($filterStatus) ? '&status=' . urlencode($filterStatus) : ''; ?>" 
                       style="background: #df2439; color: white; padding: 8px 15px; border-radius: 6px; text-decoration: none; font-size: 14px;">Next →</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function copyLink(url) {
            navigator.clipboard.writeText(url).then(() => {
                alert('Payment link copied to clipboard!');
            }).catch(err => {
                prompt('Copy this link:', url);
            });
        }
    </script>
</body>
</html>
