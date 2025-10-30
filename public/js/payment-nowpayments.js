// NOWPayments Integration
let paymentData = null;
let invoiceData = null;

// Get payment link ID from URL
const urlPath = window.location.pathname;
const paymentLinkId = urlPath.split('/').pop();

// DOM Elements
const loadingState = document.getElementById('loading-state');
const errorState = document.getElementById('error-state');
const paymentInfoSection = document.getElementById('payment-info');
const submitButton = document.getElementById('submit-button');

// Initialize on page load
document.addEventListener('DOMContentLoaded', async () => {
    try {
        // Load payment details
        await loadPaymentDetails();

        // Show payment info
        loadingState.style.display = 'none';
        paymentInfoSection.style.display = 'block';

    } catch (error) {
        console.error('Initialization error:', error);
        showError('Failed to load payment details');
    }
});

// Load payment details from backend
async function loadPaymentDetails() {
    try {
        console.log('Loading payment details for:', paymentLinkId);
        const response = await fetch(`/api/public/payment/${paymentLinkId}`);
        console.log('Response status:', response.status);
        const data = await response.json();
        console.log('Response data:', data);

        if (!response.ok || !data.payment) {
            throw new Error(data.error || data.message || 'Payment not found');
        }

        paymentData = data.payment;

        // Check if payment is already completed
        if (paymentData.status === 'completed' || paymentData.status === 'processing') {
            throw new Error('This payment has already been processed');
        }

        // Update UI with payment details
        document.getElementById('payment-amount').textContent = 
            `$${parseFloat(paymentData.amount).toFixed(2)}`;
        document.getElementById('payment-id').textContent = 
            paymentLinkId.substring(0, 16) + '...';

        // Update page title
        document.title = `Pay $${paymentData.amount} - Secure Checkout`;

    } catch (error) {
        console.error('Load payment error:', error);
        showError(error.message);
        throw error;
    }
}

// Handle pay button click
async function handlePayment() {
    try {
        // Disable button
        setLoading(true);

        // Create NOWPayments invoice
        const response = await fetch(`/api/nowpayments/create-invoice/${paymentLinkId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        });

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'Failed to create payment invoice');
        }

        invoiceData = data;

        // Redirect to NOWPayments hosted checkout page
        window.location.href = data.invoice_url;

    } catch (error) {
        console.error('Payment error:', error);
        showStatusMessage(error.message, 'error');
        setLoading(false);
    }
}

// Set button loading state
function setLoading(isLoading) {
    submitButton.disabled = isLoading;
    const btnText = submitButton.querySelector('.btn-text');
    const btnLoader = submitButton.querySelector('.btn-loader');
    
    if (isLoading) {
        btnText.textContent = 'Processing...';
        btnLoader.style.display = 'inline-block';
    } else {
        btnText.textContent = 'Pay Now';
        btnLoader.style.display = 'none';
    }
}

// Show error message
function showError(message) {
    loadingState.style.display = 'none';
    paymentInfoSection.style.display = 'none';
    errorState.style.display = 'block';
    document.getElementById('error-message').textContent = message;
}

// Show status message (banner)
function showStatusMessage(message, type = 'success') {
    const banner = document.createElement('div');
    banner.className = `status-banner ${type}`;
    banner.textContent = message;
    banner.style.cssText = `
        position: fixed;
        top: 20px;
        left: 50%;
        transform: translateX(-50%);
        padding: 15px 30px;
        background: ${type === 'success' ? '#10b981' : '#ef4444'};
        color: white;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        z-index: 9999;
        font-weight: 500;
    `;
    
    document.body.appendChild(banner);
    
    setTimeout(() => {
        banner.remove();
    }, 5000);
}

// Format currency
function formatAmount(amount, currency) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency.toUpperCase()
    }).format(amount);
}
