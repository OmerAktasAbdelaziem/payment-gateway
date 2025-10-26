const express = require('express');
const router = express.Router();

// Debug: Log the DB_TYPE environment variable
console.log('🔍 Auth Route - DB_TYPE:', process.env.DB_TYPE);

// Use MySQL auth service if DB_TYPE is set to mysql, otherwise use SQLite
const authService = process.env.DB_TYPE === 'mysql'
  ? require('../services/auth-mysql')
  : require('../services/auth');

console.log('🔍 Auth Service loaded:', process.env.DB_TYPE === 'mysql' ? 'MySQL' : 'SQLite');// Login endpoint
router.post('/login', async (req, res) => {
    console.log('\n🔐 ===== LOGIN ATTEMPT =====');
    console.log('📥 Request body:', req.body);
    console.log('🍪 Session ID:', req.sessionID);
    console.log('🍪 Session before login:', req.session);
    
    try {
        const { username, password } = req.body;

        if (!username || !password) {
            console.log('❌ Missing credentials');
            return res.status(400).json({ error: 'Username and password are required' });
        }

        console.log('🔍 Authenticating user:', username);
        const user = await authService.authenticateUser(username, password);
        console.log('👤 Auth result:', user ? 'SUCCESS' : 'FAILED');

        if (!user) {
            console.log('❌ Invalid credentials');
            return res.status(401).json({ error: 'Invalid username or password' });
        }

        // Set session and save it
        console.log('💾 Setting session data...');
        req.session.userId = user.id;
        req.session.username = user.username;
        req.session.role = user.role;
        console.log('🍪 Session after setting data:', req.session);

        // Save session before responding
        req.session.save((err) => {
            if (err) {
                console.error('❌ Session save error:', err);
                return res.status(500).json({ error: 'Login failed - session error' });
            }

            console.log('✅ Session saved successfully');
            console.log('🍪 Session ID after save:', req.sessionID);
            console.log('📤 Sending success response');
            
            res.json({ 
                success: true, 
                user: {
                    username: user.username,
                    email: user.email,
                    role: user.role
                }
            });
            
            console.log('🔐 ===== LOGIN COMPLETE =====\n');
        });
    } catch (error) {
        console.error('💥 Login exception:', error);
        res.status(500).json({ error: 'Login failed' });
    }
});

// Logout endpoint
router.post('/logout', (req, res) => {
    req.session.destroy((err) => {
        if (err) {
            return res.status(500).json({ error: 'Logout failed' });
        }
        res.json({ success: true });
    });
});

// Check authentication status
router.get('/check', (req, res) => {
    console.log('\n🔍 ===== AUTH CHECK =====');
    console.log('🍪 Session ID:', req.sessionID);
    console.log('🍪 Session data:', req.session);
    console.log('👤 User ID in session:', req.session.userId);
    
    if (req.session.userId) {
        console.log('✅ User is authenticated');
        res.json({ 
            authenticated: true,
            user: {
                username: req.session.username,
                role: req.session.role
            }
        });
    } else {
        console.log('❌ User is NOT authenticated');
        res.json({ authenticated: false });
    }
    console.log('🔍 ===== AUTH CHECK COMPLETE =====\n');
});

// Change password
router.post('/change-password', async (req, res) => {
    try {
        if (!req.session.userId) {
            return res.status(401).json({ error: 'Not authenticated' });
        }

        const { currentPassword, newPassword } = req.body;

        if (!currentPassword || !newPassword) {
            return res.status(400).json({ error: 'Current and new passwords are required' });
        }

        // Verify current password
        const user = await authService.getUserById(req.session.userId);
        const isValid = await authService.authenticateUser(user.username, currentPassword);

        if (!isValid) {
            return res.status(401).json({ error: 'Current password is incorrect' });
        }

        // Change password
        await authService.changePassword(req.session.userId, newPassword);

        res.json({ success: true, message: 'Password changed successfully' });
    } catch (error) {
        console.error('Change password error:', error);
        res.status(500).json({ error: 'Failed to change password' });
    }
});

module.exports = router;
