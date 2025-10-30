<?php
/**
 * Authentication Class
 * Handles user authentication and session management
 */

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->startSession();
    }
    
    /**
     * Start secure session
     */
    private function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_samesite', 'Lax');
            
            if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
                ini_set('session.cookie_secure', 1);
            }
            
            session_name(SESSION_NAME);
            session_start();
        }
    }
    
    /**
     * Login user
     */
    public function login($username, $password) {
        // Get user from database
        $user = $this->db->fetchOne(
            "SELECT * FROM users WHERE username = ? LIMIT 1",
            [$username]
        );
        
        if (!$user) {
            return [
                'success' => false,
                'message' => 'Invalid username or password'
            ];
        }
        
        // Verify password
        if (!password_verify($password, $user['password'])) {
            return [
                'success' => false,
                'message' => 'Invalid username or password'
            ];
        }
        
        // Update last login
        $this->db->execute(
            "UPDATE users SET last_login = NOW() WHERE id = ?",
            [$user['id']]
        );
        
        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        
        // Regenerate session ID for security
        session_regenerate_id(true);
        
        return [
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'id' => $user['id'],
                'username' => $user['username']
            ]
        ];
    }
    
    /**
     * Logout user
     */
    public function logout() {
        $_SESSION = [];
        
        // Delete session cookie
        if (isset($_COOKIE[SESSION_NAME])) {
            setcookie(SESSION_NAME, '', time() - 3600, '/');
        }
        
        session_destroy();
        
        return [
            'success' => true,
            'message' => 'Logged out successfully'
        ];
    }
    
    /**
     * Check if user is authenticated
     */
    public function isAuthenticated() {
        if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            return false;
        }
        
        // Check session timeout
        if (isset($_SESSION['login_time'])) {
            $sessionAge = time() - $_SESSION['login_time'];
            if ($sessionAge > SESSION_LIFETIME) {
                $this->logout();
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get current user
     */
    public function getCurrentUser() {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? null
        ];
    }
    
    /**
     * Require authentication (middleware)
     */
    public function requireAuth() {
        if (!$this->isAuthenticated()) {
            http_response_code(401);
            echo json_encode([
                'error' => 'Unauthorized',
                'message' => 'Authentication required'
            ]);
            exit;
        }
    }
    
    /**
     * Create new user (for initial setup)
     */
    public function createUser($username, $password) {
        // Check if user exists
        $existing = $this->db->fetchOne(
            "SELECT id FROM users WHERE username = ?",
            [$username]
        );
        
        if ($existing) {
            return [
                'success' => false,
                'message' => 'Username already exists'
            ];
        }
        
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_HASH_ALGO, [
            'cost' => PASSWORD_HASH_COST
        ]);
        
        // Insert user
        $userId = $this->db->insert(
            "INSERT INTO users (username, password, created_at) VALUES (?, ?, NOW())",
            [$username, $hashedPassword]
        );
        
        return [
            'success' => true,
            'message' => 'User created successfully',
            'user_id' => $userId
        ];
    }
}
