// Middleware to check if user is authenticated
function requireAuth(req, res, next) {
    if (req.session && req.session.userId) {
        next();
    } else {
        // If it's an API request, return JSON error
        if (req.path.startsWith('/api/')) {
            return res.status(401).json({ error: 'Authentication required' });
        }
        // If it's a page request, redirect to login
        res.redirect('/login');
    }
}

// Middleware to check if user is already logged in
function redirectIfAuthenticated(req, res, next) {
    if (req.session && req.session.userId) {
        res.redirect('/admin');
    } else {
        next();
    }
}

module.exports = {
    requireAuth,
    redirectIfAuthenticated
};
