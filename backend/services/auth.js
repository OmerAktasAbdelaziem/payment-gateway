const bcrypt = require('bcrypt');
const { getDb } = require('./database');

class AuthService {
    constructor() {
        this.initialized = false;
    }

    async initialize() {
        if (this.initialized) return;
        
        const db = getDb();
        await this.initializeUsersTable();
        this.initialized = true;
    }

    async initializeUsersTable() {
        const db = getDb();
        return new Promise((resolve, reject) => {
            db.run(`
                CREATE TABLE IF NOT EXISTS users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    username TEXT UNIQUE NOT NULL,
                    password_hash TEXT NOT NULL,
                    email TEXT,
                    role TEXT DEFAULT 'admin',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    last_login DATETIME
                )
            `, (err) => {
                if (err) {
                    console.error('❌ Error creating users table:', err);
                    reject(err);
                } else {
                    console.log('✅ Users table ready');
                    this.seedDefaultAdmin();
                    resolve();
                }
            });
        });
    }

    async seedDefaultAdmin() {
        const db = getDb();
        // Check if admin user exists
        db.get('SELECT id FROM users WHERE username = ?', ['admin'], async (err, row) => {
            if (err) {
                console.error('❌ Error checking for admin user:', err);
                return;
            }

            if (!row) {
                // Create default admin user
                const defaultPassword = 'Admin@2025';
                const passwordHash = await bcrypt.hash(defaultPassword, 10);

                db.run(
                    'INSERT INTO users (username, password_hash, email, role) VALUES (?, ?, ?, ?)',
                    ['admin', passwordHash, 'admin@internationalpro.com', 'admin'],
                    (err) => {
                        if (err) {
                            console.error('❌ Error creating admin user:', err);
                        } else {
                            console.log('✅ Default admin user created');
                            console.log('   Username: admin');
                            console.log('   Password: Admin@2025');
                            console.log('   ⚠️  Please change this password after first login!');
                        }
                    }
                );
            }
        });
    }

    async authenticateUser(username, password) {
        const db = getDb();
        return new Promise((resolve, reject) => {
            db.get('SELECT * FROM users WHERE username = ?', [username], async (err, user) => {
                if (err) {
                    reject(err);
                    return;
                }

                if (!user) {
                    resolve(null);
                    return;
                }

                const isValid = await bcrypt.compare(password, user.password_hash);
                if (isValid) {
                    // Update last login
                    db.run('UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?', [user.id]);
                    resolve({
                        id: user.id,
                        username: user.username,
                        email: user.email,
                        role: user.role
                    });
                } else {
                    resolve(null);
                }
            });
        });
    }

    async changePassword(userId, newPassword) {
        const db = getDb();
        const passwordHash = await bcrypt.hash(newPassword, 10);
        return new Promise((resolve, reject) => {
            db.run(
                'UPDATE users SET password_hash = ? WHERE id = ?',
                [passwordHash, userId],
                function(err) {
                    if (err) reject(err);
                    else resolve(this.changes > 0);
                }
            );
        });
    }

    async createUser(username, password, email, role = 'admin') {
        const db = getDb();
        const passwordHash = await bcrypt.hash(password, 10);
        return new Promise((resolve, reject) => {
            db.run(
                'INSERT INTO users (username, password_hash, email, role) VALUES (?, ?, ?, ?)',
                [username, passwordHash, email, role],
                function(err) {
                    if (err) reject(err);
                    else resolve(this.lastID);
                }
            );
        });
    }

    async getUserById(userId) {
        const db = getDb();
        return new Promise((resolve, reject) => {
            db.get('SELECT id, username, email, role, created_at, last_login FROM users WHERE id = ?', 
                [userId], 
                (err, user) => {
                    if (err) reject(err);
                    else resolve(user);
                }
            );
        });
    }
}

module.exports = new AuthService();
