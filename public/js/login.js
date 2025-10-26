document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('login-form');
    const usernameInput = document.getElementById('username');
    const passwordInput = document.getElementById('password');
    const loginButton = document.getElementById('login-button');
    const buttonText = document.getElementById('button-text');
    const buttonSpinner = document.getElementById('button-spinner');
    const errorMessage = document.getElementById('error-message');

    // Check if already logged in
    checkAuthStatus();

    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        await handleLogin();
    });

    async function checkAuthStatus() {
        try {
            const response = await fetch('/api/auth/check');
            const data = await response.json();
            
            if (data.authenticated) {
                window.location.href = '/admin';
            }
        } catch (error) {
            console.error('Error checking auth status:', error);
        }
    }

    async function handleLogin() {
        const username = usernameInput.value.trim();
        const password = passwordInput.value;

        if (!username || !password) {
            showError('Please enter both username and password');
            return;
        }

        // Show loading state
        setLoading(true);
        hideError();

        try {
            const response = await fetch('/api/auth/login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ username, password })
            });

            const data = await response.json();

            if (response.ok && data.success) {
                // Login successful
                showSuccess();
                setTimeout(() => {
                    window.location.href = '/admin';
                }, 500);
            } else {
                // Login failed
                showError(data.error || 'Invalid username or password');
                setLoading(false);
                passwordInput.value = '';
                passwordInput.focus();
            }
        } catch (error) {
            console.error('Login error:', error);
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
