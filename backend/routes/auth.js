const express = require('express');
const router = express.Router();
// Use MySQL auth service if DB_TYPE is set to mysql, otherwise use SQLite
const authService = process.env.DB_TYPE === 'mysql' 
  ? require('../services/auth-mysql')
  : require('../services/auth');

// Login endpoint
router.post('/login', async (req, res) => {
    try {
        const { username, password } = req.body;

        if (!username || !password) {
            return res.status(400).json({ error: 'Username and password are required' });
        }

        const user = await authService.authenticateUser(username, password);

        if (!user) {
            return res.status(401).json({ error: 'Invalid username or password' });
        }

        // Set session and save it
        req.session.userId = user.id;
        req.session.username = user.username;
        req.session.role = user.role;

        // Save session before responding
        req.session.save((err) => {
            if (err) {
                console.error('Session save error:', err);
                return res.status(500).json({ error: 'Login failed - session error' });
            }

            res.json({ 
                success: true, 
                user: {
                    username: user.username,
                    email: user.email,
                    role: user.role
                }
            });
        });
    } catch (error) {
        console.error('Login error:', error);
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
    if (req.session.userId) {
        res.json({ 
            authenticated: true,
            user: {
                username: req.session.username,
                role: req.session.role
            }
        });
    } else {
        res.json({ authenticated: false });
    }
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
