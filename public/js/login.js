// Script loaded successfully
console.log('🚀 Login.js loaded!');
console.error('🔴 TESTING: If you see this, JavaScript is working!');

document.addEventListener('DOMContentLoaded', () => {
    console.log('✅ DOM Content Loaded');
    
    const loginForm = document.getElementById('login-form');
    const usernameInput = document.getElementById('username');
    const passwordInput = document.getElementById('password');
    const loginButton = document.getElementById('login-button');
    const buttonText = document.getElementById('button-text');
    const buttonSpinner = document.getElementById('button-spinner');
    const errorMessage = document.getElementById('error-message');

    console.log('📋 Form elements:', {
        loginForm: !!loginForm,
        usernameInput: !!usernameInput,
        passwordInput: !!passwordInput,
        loginButton: !!loginButton
    });

    if (!loginForm) {
        console.error('❌ Login form not found!');
        return;
    }

    // Check if already logged in
    checkAuthStatus();

    loginForm.addEventListener('submit', async (e) => {
        console.log('📝 Form submit event triggered');
        e.preventDefault();
        e.stopPropagation();
        console.log('🛑 Default form submission prevented');
        
        try {
            await handleLogin();
        } catch (error) {
            console.error('💥 Error in form submit handler:', error);
        }
        
        return false; // Extra safety to prevent form submission
    });

    async function checkAuthStatus() {
        console.log('🔍 Checking auth status...');
        try {
            const response = await fetch('/api/auth/check', {
                credentials: 'include'
            });
            const data = await response.json();
            console.log('🔍 Auth check result:', data);
            
            if (data.authenticated) {
                console.log('✅ Already authenticated, redirecting to admin...');
                window.location.href = '/admin';
            } else {
                console.log('❌ Not authenticated');
            }
        } catch (error) {
            console.error('💥 Error checking auth status:', error);
        }
    }

    async function handleLogin() {
        const username = usernameInput.value.trim();
        const password = passwordInput.value;

        console.log('🔐 Frontend: Starting login...', { username });

        if (!username || !password) {
            console.log('❌ Frontend: Missing credentials');
            showError('Please enter both username and password');
            return;
        }

        // Show loading state
        setLoading(true);
        hideError();

        try {
            console.log('📤 Frontend: Sending login request...');
            const response = await fetch('/api/auth/login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'include', // Important for cookies
                body: JSON.stringify({ username, password })
            });

            console.log('📥 Frontend: Response received', {
                status: response.status,
                ok: response.ok,
                headers: Object.fromEntries(response.headers.entries())
            });

            const data = await response.json();
            console.log('📥 Frontend: Response data:', data);

            if (response.ok && data.success) {
                // Login successful
                console.log('✅ Frontend: Login successful! Redirecting...');
                showSuccess();
                setTimeout(() => {
                    console.log('🔀 Frontend: Redirecting to /admin');
                    window.location.href = '/admin';
                }, 500);
            } else {
                // Login failed
                console.log('❌ Frontend: Login failed', data.error);
                showError(data.error || 'Invalid username or password');
                setLoading(false);
                passwordInput.value = '';
                passwordInput.focus();
            }
        } catch (error) {
            console.error('💥 Frontend: Login exception:', error);
            showError('Connection error. Please try again.');
            setLoading(false);
        }
    }

    function setLoading(loading) {
        loginButton.disabled = loading;
        buttonText.style.display = loading ? 'none' : 'inline';
        buttonSpinner.style.display = loading ? 'inline-block' : 'none';
        
        if (loading) {
            usernameInput.disabled = true;
            passwordInput.disabled = true;
        } else {
            usernameInput.disabled = false;
            passwordInput.disabled = false;
        }
    }

    function showError(message) {
        errorMessage.textContent = message;
        errorMessage.style.display = 'flex';
        errorMessage.style.animation = 'shake 0.4s ease';
    }

    function hideError() {
        errorMessage.style.display = 'none';
    }

    function showSuccess() {
        buttonText.textContent = '✓ Success!';
        loginButton.style.background = '#10b981';
    }

    // Auto-focus username input
    usernameInput.focus();
});

// Add shake animation
const style = document.createElement('style');
style.textContent = `
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-10px); }
        75% { transform: translateX(10px); }
    }
`;
document.head.appendChild(style);
