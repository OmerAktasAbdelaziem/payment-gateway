// Initialize Stripe (publishable key will be loaded from backend)
let stripe;
let cardElement;
let paymentData = null;

// Get payment link ID from URL
const urlPath = window.location.pathname;
const paymentLinkId = urlPath.split('/').pop();

// DOM Elements
const loadingState = document.getElementById('loading-state');
const errorState = document.getElementById('error-state');
const paymentForm = document.getElementById('payment-form');
const submitButton = document.getElementById('submit-button');
const cardErrors = document.getElementById('card-errors');

// Initialize on page load
document.addEventListener('DOMContentLoaded', async () => {
    try {
        // Load Stripe publishable key (LIVE MODE)
        const stripeKey = 'pk_live_51SLloUHqjjklN91QyaOQqGaVfcr0VZBRc5JDY8KhpU2BYEZn7FHhGHjojcN9BfWLCMNigRFrXQcOSJwHgRvhBEMI00YjU7fzAl';
        stripe = Stripe(stripeKey);

        // Load payment details
        await loadPaymentDetails();

        // Initialize Stripe Elements
        initializeStripeElements();

        // Show form
        loadingState.style.display = 'none';
        paymentForm.parentElement.style.display = 'block';

    } catch (error) {
        console.error('Initialization error:', error);
        showError('Failed to initialize payment form');
    }
});

// Load payment details from backend
async function loadPaymentDetails() {
    try {
        const response = await fetch(`/api/public/payment/${paymentLinkId}`);
        const data = await response.json();

        if (!response.ok || !data.payment) {
            throw new Error(data.error || 'Payment not found');
        }

        paymentData = data.payment;

        // Check if payment is already completed
        if (paymentData.status === 'completed' || paymentData.status === 'processing') {
            showError('This payment has already been completed');
            return;
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

// Initialize Stripe Elements
function initializeStripeElements() {
    const elements = stripe.elements();

    // Create card element with custom styling
    cardElement = elements.create('card', {
        style: {
            base: {
                fontSize: '16px',
                color: '#1e293b',
                fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                '::placeholder': {
                    color: '#94a3b8',
                },
                iconColor: '#667eea',
            },
            invalid: {
                color: '#ef4444',
                iconColor: '#ef4444',
            },
        },
        hidePostalCode: false,
    });

    // Mount card element
    cardElement.mount('#card-element');

    // Handle real-time validation errors
    cardElement.on('change', (event) => {
        if (event.error) {
            cardErrors.textContent = event.error.message;
        } else {
            cardErrors.textContent = '';
        }
    });

    // Handle form submission
    paymentForm.addEventListener('submit', handleSubmit);
}

// Handle form submission
async function handleSubmit(event) {
    event.preventDefault();

    // Disable submit button
    setLoading(true);

    const email = document.getElementById('email').value;

    try {
        // Step 1: Create Payment Intent
        const paymentIntent = await createPaymentIntent();

        if (!paymentIntent.success) {
            throw new Error(paymentIntent.error || 'Failed to create payment intent');
        }

        // Step 2: Confirm card payment
        const result = await stripe.confirmCardPayment(paymentIntent.clientSecret, {
            payment_method: {
                card: cardElement,
                billing_details: {
                    email: email,
                },
            },
        });

        if (result.error) {
            // Show error to customer
            throw new Error(result.error.message);
        } else {
            // Payment successful!
            if (result.paymentIntent.status === 'succeeded') {
                handlePaymentSuccess(result.paymentIntent);
            }
        }

    } catch (error) {
        console.error('Payment error:', error);
        showStatusMessage(error.message, 'error');
        setLoading(false);
    }
}

// Create Payment Intent
async function createPaymentIntent() {
    try {
        const response = await fetch(`/api/stripe/create-intent/${paymentLinkId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        });

        const data = await response.json();
        return data;

    } catch (error) {
        console.error('Create payment intent error:', error);
        return {
            success: false,
            error: error.message,
        };
    }
}

// Handle successful payment
function handlePaymentSuccess(paymentIntent) {
    console.log('Payment successful!', paymentIntent);

    // Show success message
    showStatusMessage('Payment successful! Redirecting...', 'success');

    // Redirect to success page after 2 seconds
    setTimeout(() => {
        window.location.href = `/success.html?payment_id=${paymentIntent.id}`;
    }, 2000);
}

// Set loading state
function setLoading(loading) {
    submitButton.disabled = loading;
    
    if (loading) {
        submitButton.classList.add('loading');
    } else {
        submitButton.classList.remove('loading');
    }

    // Disable card element during loading
    if (cardElement) {
        cardElement.update({ disabled: loading });
    }
}

// Show status message
function showStatusMessage(message, type) {
    const statusMessage = document.getElementById('status-message');
    statusMessage.textContent = message;
    statusMessage.className = `status-message ${type}`;
    statusMessage.style.display = 'block';

    // Auto-hide after 5 seconds for error messages
    if (type === 'error') {
        setTimeout(() => {
            statusMessage.style.display = 'none';
        }, 5000);
    }
}

// Show error state
function showError(message) {
    loadingState.style.display = 'none';
    paymentForm.parentElement.style.display = 'none';
    errorState.style.display = 'block';
    document.getElementById('error-message').textContent = message;
}
