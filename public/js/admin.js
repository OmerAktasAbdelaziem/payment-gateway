// Global variables
let allPayments = [];
let generatedPaymentUrl = '';

// Sync Stripe transactions
async function syncStripeTransactions() {
    try {
        const response = await fetch('/api/sync-stripe', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(`✅ Sync Complete!\n\nNew: ${result.synced}\nUpdated: ${result.updated}\nTotal: ${result.total}`);
            // Refresh data
            loadDashboard();
            loadPayments();
        } else {
            alert(`❌ Sync Failed: ${result.error}`);
        }
    } catch (error) {
        console.error('Sync error:', error);
        alert('❌ Failed to sync Stripe transactions');
    }
}

// Initialize dashboard
document.addEventListener('DOMContentLoaded', () => {
    loadDashboard();
    loadStats();
    loadPayments();
    setupFormHandler();
});

// Load dashboard data
async function loadDashboard() {
    await loadBalances();
    await loadAnalytics();
    await loadRecentTransactions();
}

// Load balances (Stripe and Wallet)
async function loadBalances() {
    // Stripe Balance (simulated - in production, you'd call Stripe API)
    const stripeBalanceEl = document.getElementById('stripe-balance');
    const stripePendingEl = document.getElementById('stripe-pending');
    
    if (!stripeBalanceEl || !stripePendingEl) {
        console.error('Balance elements not found');
        return;
    }
    
    // For now, calculate from payments
    try {
        const response = await fetch('/api/payments');
        
        if (!response.ok) {
            throw new Error('Failed to fetch payments');
        }
        
        const payments = await response.json();
        
        // Ensure payments is an array
        const paymentsArray = Array.isArray(payments) ? payments : [];
        
        const completedPayments = paymentsArray.filter(p => p && p.status === 'completed');
        const pendingPayments = paymentsArray.filter(p => p && p.status === 'pending');
        
        const availableBalance = completedPayments.reduce((sum, p) => sum + (parseFloat(p.amount) || 0), 0);
        const pendingBalance = pendingPayments.reduce((sum, p) => sum + (parseFloat(p.amount) || 0), 0);
        
        stripeBalanceEl.innerHTML = `$${availableBalance.toFixed(2)}`;
        stripePendingEl.textContent = `Pending: $${pendingBalance.toFixed(2)}`;
    } catch (error) {
        console.error('Error loading balances:', error);
        stripeBalanceEl.innerHTML = `$0.00`;
        stripePendingEl.textContent = `Pending: $0.00`;
    }
    
    // Wallet Balance
    const walletBalanceEl = document.getElementById('wallet-balance');
    const walletNetworkEl = document.getElementById('wallet-network');
    
    if (walletBalanceEl && walletNetworkEl) {
        // Check if Binance is configured
        walletBalanceEl.innerHTML = `<span class="amount-loading">Simulation Mode</span>`;
        walletNetworkEl.textContent = `Network: TRC20`;
    }
}

// Load analytics data
async function loadAnalytics() {
    try {
        const response = await fetch('/api/payments');
        const payments = await response.json();
        
        // Ensure payments is an array
        const paymentsArray = Array.isArray(payments) ? payments : [];
        
        const now = new Date();
        const todayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        const yesterdayStart = new Date(todayStart);
        yesterdayStart.setDate(yesterdayStart.getDate() - 1);
        const weekStart = new Date(todayStart);
        weekStart.setDate(weekStart.getDate() - 7);
        const monthStart = new Date(todayStart);
        monthStart.setDate(monthStart.getDate() - 30);
        
        // Filter completed payments
        const completed = paymentsArray.filter(p => p && p.status === 'completed');
        
        // Calculate revenues
        const todayRevenue = completed
            .filter(p => new Date(p.created_at) >= todayStart)
            .reduce((sum, p) => sum + p.amount, 0);
            
        const yesterdayRevenue = completed
            .filter(p => {
                const date = new Date(p.created_at);
                return date >= yesterdayStart && date < todayStart;
            })
            .reduce((sum, p) => sum + p.amount, 0);
            
        const weekRevenue = completed
            .filter(p => new Date(p.created_at) >= weekStart)
            .reduce((sum, p) => sum + p.amount, 0);
            
        const monthRevenue = completed
            .filter(p => new Date(p.created_at) >= monthStart)
            .reduce((sum, p) => sum + p.amount, 0);
            
        const totalRevenue = completed.reduce((sum, p) => sum + p.amount, 0);
        
        // Update UI
        document.getElementById('today-revenue').textContent = `$${todayRevenue.toFixed(2)}`;
        document.getElementById('week-revenue').textContent = `$${weekRevenue.toFixed(2)}`;
        document.getElementById('month-revenue').textContent = `$${monthRevenue.toFixed(2)}`;
        document.getElementById('total-revenue-dashboard').textContent = `$${totalRevenue.toFixed(2)}`;
        
        // Calculate changes
        const todayChange = yesterdayRevenue > 0 
            ? ((todayRevenue - yesterdayRevenue) / yesterdayRevenue * 100).toFixed(1)
            : 0;
        const todayChangeEl = document.getElementById('today-change');
        todayChangeEl.textContent = `${todayChange > 0 ? '+' : ''}${todayChange}%`;
        todayChangeEl.className = `analytics-change ${todayChange >= 0 ? 'positive' : 'negative'}`;
        
    } catch (error) {
        console.error('Error loading analytics:', error);
    }
}

// Load recent transactions (last 15)
async function loadRecentTransactions() {
    try {
        const response = await fetch('/api/payments');
        const payments = await response.json();
        
        // Ensure payments is an array
        const paymentsArray = Array.isArray(payments) ? payments : [];
        
        // Sort by date and take last 15
        const recentPayments = paymentsArray
            .sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
            .slice(0, 15);
        
        const tbody = document.getElementById('recent-transactions');
        
        if (!tbody) {
            console.error('Recent transactions tbody not found');
            return;
        }
        
        if (recentPayments.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="no-data">No transactions yet</td></tr>';
            return;
        }
        
        tbody.innerHTML = recentPayments.map(payment => `
            <tr>
                <td>${new Date(payment.created_at).toLocaleDateString()}</td>
                <td><span class="transaction-id">${(payment.payment_link_id || '').substring(0, 12)}...</span></td>
                <td>
                    <div class="customer-info">
                        <span class="customer-name">${payment.customer_name || 'N/A'}</span>
                        <span class="customer-email">${payment.customer_email || 'N/A'}</span>
                    </div>
                </td>
                <td class="amount-cell">$${(payment.amount || 0).toFixed(2)} ${payment.currency || 'USD'}</td>
                <td>${getStatusBadge(payment.status)}</td>
                <td>${getUSDTBadge(payment.usdt_conversion_status)}</td>
                <td>
                    <button class="action-btn" onclick="viewPayment('${payment.payment_link_id}')">View</button>
                </td>
            </tr>
        `).join('');
        
    } catch (error) {
        console.error('Error loading recent transactions:', error);
    }
}

// Helper function for USDT badge
function getUSDTBadge(status) {
    if (!status || status === 'pending') {
        return '<span class="usdt-badge pending">⏳ Pending</span>';
    } else if (status === 'completed') {
        return '<span class="usdt-badge">✓ Converted</span>';
    } else if (status === 'failed') {
        return '<span class="usdt-badge failed">✗ Failed</span>';
    }
    return '<span class="usdt-badge pending">-</span>';
}

// View payment details
function viewPayment(linkId) {
    // Switch to payments section and highlight the payment
    showSection('payments');
    // You can add more logic here to highlight or filter the specific payment
}

// Setup form submission handler
function setupFormHandler() {
    const form = document.getElementById('generate-form');
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        await generatePaymentLink();
    });
}

// Generate payment link
async function generatePaymentLink() {
    const amount = document.getElementById('amount').value;
    const currency = document.getElementById('currency').value;
    const customerEmail = document.getElementById('customer-email').value;
    const customerName = document.getElementById('customer-name').value;

    // Validate
    if (!amount || amount <= 0) {
        alert('Please enter a valid amount');
        return;
    }

    // Show loading
    const submitBtn = document.querySelector('#generate-form .btn-primary');
    submitBtn.disabled = true;
    submitBtn.querySelector('.btn-text').style.display = 'none';
    submitBtn.querySelector('.btn-loader').style.display = 'inline';

    try {
        const response = await fetch('/api/create-payment-link', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                amount: parseFloat(amount),
                currency,
                customer_email: customerEmail || null,
                customer_name: customerName || null,
            }),
        });

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'Failed to generate payment link');
        }

        // Show generated link
        generatedPaymentUrl = data.payment_url;
        document.getElementById('payment-url').value = generatedPaymentUrl;
        document.getElementById('generated-link').style.display = 'block';

        // Scroll to generated link
        document.getElementById('generated-link').scrollIntoView({ 
            behavior: 'smooth', 
            block: 'nearest' 
        });

        // Refresh stats and payments
        loadStats();
        if (document.getElementById('payments-section').classList.contains('active')) {
            loadPayments();
        }

    } catch (error) {
        console.error('Generate link error:', error);
        alert('Error: ' + error.message);
    } finally {
        // Reset button
        submitBtn.disabled = false;
        submitBtn.querySelector('.btn-text').style.display = 'inline';
        submitBtn.querySelector('.btn-loader').style.display = 'none';
    }
}

// Copy link to clipboard
function copyLink() {
    const input = document.getElementById('payment-url');
    input.select();
    document.execCommand('copy');

    // Show feedback
    const btn = event.target;
    const originalText = btn.textContent;
    btn.textContent = '✓ Copied!';
    btn.style.background = '#10b981';

    setTimeout(() => {
        btn.textContent = originalText;
        btn.style.background = '';
    }, 2000);
}

// Open payment link
function openLink() {
    if (generatedPaymentUrl) {
        window.open(generatedPaymentUrl, '_blank');
    }
}

// Share via email
function shareLink() {
    const amount = document.getElementById('amount').value;
    const customerEmail = document.getElementById('customer-email').value;

    const subject = encodeURIComponent('Payment Request');
    const body = encodeURIComponent(
        `Please complete your payment of $${amount} using the following secure link:\n\n${generatedPaymentUrl}\n\nThank you!`
    );

    const mailtoLink = `mailto:${customerEmail}?subject=${subject}&body=${body}`;
    window.location.href = mailtoLink;
}

// Reset form
function resetForm() {
    document.getElementById('generate-form').reset();
    document.getElementById('generated-link').style.display = 'none';
    generatedPaymentUrl = '';
}

// Load statistics
async function loadStats() {
    try {
        const response = await fetch('/api/payments');
        const data = await response.json();

        if (!data.success) {
            throw new Error('Failed to load stats');
        }

        const payments = data.payments;

        // Calculate stats
        const completed = payments.filter(p => p.status === 'completed');
        const pending = payments.filter(p => p.status === 'pending' || p.status === 'pending_payment' || p.status === 'processing');
        const failed = payments.filter(p => p.status === 'failed');

        const totalRevenue = completed.reduce((sum, p) => sum + parseFloat(p.amount), 0);

        // Update UI
        document.getElementById('total-revenue').textContent = `$${totalRevenue.toFixed(2)}`;
        document.getElementById('successful-count').textContent = completed.length;
        document.getElementById('pending-count').textContent = pending.length;
        document.getElementById('failed-count').textContent = failed.length;

    } catch (error) {
        console.error('Load stats error:', error);
    }
}

// Load payments
async function loadPayments() {
    const tbody = document.getElementById('payments-tbody');

    try {
        const response = await fetch('/api/payments?limit=50');
        const data = await response.json();

        if (!data.success) {
            throw new Error('Failed to load payments');
        }

        allPayments = data.payments;
        displayPayments(allPayments);

    } catch (error) {
        console.error('Load payments error:', error);
        tbody.innerHTML = `
            <tr>
                <td colspan="6" style="text-align: center; color: #ef4444; padding: 40px;">
                    Error loading payments: ${error.message}
                </td>
            </tr>
        `;
    }
}

// Display payments in table
function displayPayments(payments) {
    const tbody = document.getElementById('payments-tbody');

    if (payments.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" style="text-align: center; padding: 40px; color: #64748b;">
                    No payments found
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = payments.map(payment => `
        <tr>
            <td>
                <span style="font-family: 'Courier New', monospace; font-size: 12px;">
                    ${payment.payment_link_id.substring(0, 16)}...
                </span>
            </td>
            <td>
                <strong>$${parseFloat(payment.amount).toFixed(2)}</strong>
                <span style="font-size: 12px; color: #64748b; display: block;">
                    ${payment.currency}
                </span>
            </td>
            <td>
                <span class="status-badge ${payment.status}">
                    ${formatStatus(payment.status)}
                </span>
            </td>
            <td>
                ${payment.usdt_amount 
                    ? `${parseFloat(payment.usdt_amount).toFixed(2)} USDT` 
                    : '-'
                }
            </td>
            <td>
                ${formatDate(payment.created_at)}
            </td>
            <td>
                <button class="btn-icon" onclick="viewPayment('${payment.payment_id}')" title="View Details">
                    👁️
                </button>
            </td>
        </tr>
    `).join('');
}

// Filter payments
function filterPayments() {
    const statusFilter = document.getElementById('status-filter').value;

    let filtered = allPayments;

    if (statusFilter) {
        filtered = allPayments.filter(p => p.status === statusFilter);
    }

    displayPayments(filtered);
}

// View payment details
function viewPayment(paymentId) {
    const payment = allPayments.find(p => p.payment_id === paymentId);
    if (!payment) return;

    alert(`Payment Details:\n\n` +
          `Payment ID: ${payment.payment_id}\n` +
          `Amount: $${payment.amount} ${payment.currency}\n` +
          `Status: ${formatStatus(payment.status)}\n` +
          `Created: ${formatDate(payment.created_at)}\n` +
          `${payment.customer_email ? `Email: ${payment.customer_email}\n` : ''}` +
          `${payment.usdt_amount ? `USDT Amount: ${payment.usdt_amount}\n` : ''}`
    );
}

// Format status
function formatStatus(status) {
    const statusMap = {
        'pending': 'Pending',
        'pending_payment': 'Awaiting Payment',
        'processing': 'Processing',
        'completed': 'Completed',
        'failed': 'Failed',
        'canceled': 'Canceled',
        'refunded': 'Refunded'
    };
    return statusMap[status] || status;
}

// Format date
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Show section
function showSection(sectionName) {
    // Update nav
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.remove('active');
    });
    event.target.closest('.nav-item').classList.add('active');

    // Update sections
    document.querySelectorAll('.section').forEach(section => {
        section.classList.remove('active');
    });
    document.getElementById(`${sectionName}-section`).classList.add('active');

    // Update title
    const titles = {
        'dashboard': 'Dashboard',
        'generator': 'Generate Payment Link',
        'payments': 'Payment History',
        'settings': 'Settings'
    };
    document.getElementById('page-title').textContent = titles[sectionName];

    // Load data if needed
    if (sectionName === 'dashboard') {
        loadDashboard();
    } else if (sectionName === 'payments') {
        loadPayments();
    }
}

// Refresh data
function refreshData() {
    const activeSection = document.querySelector('.section.active').id;
    
    if (activeSection === 'dashboard-section') {
        loadDashboard();
    } else if (activeSection === 'generator-section') {
        loadStats();
    } else if (activeSection === 'payments-section') {
        loadPayments();
        loadStats();
    }

    // Show feedback
    const btn = event.target;
    btn.style.transform = 'rotate(360deg)';
    setTimeout(() => {
        btn.style.transform = '';
    }, 500);
}
