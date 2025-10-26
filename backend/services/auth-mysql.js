const bcrypt = require('bcrypt');
const { getDb } = require('./database-mysql');

class AuthService {
    constructor() {
        this.initialized = false;
    }

    async initialize() {
        if (this.initialized) return;
        await this.seedDefaultAdmin();
        this.initialized = true;
    }

    async seedDefaultAdmin() {
        const db = getDb();
        
        try {
            // Check if admin user exists
            const [rows] = await db.execute('SELECT id FROM users WHERE username = ?', ['admin']);
            
            if (rows.length === 0) {
                // Create default admin user
                const defaultPassword = 'Admin@2025';
                const passwordHash = await bcrypt.hash(defaultPassword, 10);

                await db.execute(
                    'INSERT INTO users (username, password_hash, email, role) VALUES (?, ?, ?, ?)',
                    ['admin', passwordHash, 'admin@internationalpro.com', 'admin']
                );
                
                console.log('✅ Default admin user created');
                console.log('   Username: admin');
                console.log('   Password: Admin@2025');
                console.log('   ⚠️  Please change this password after first login!');
            }
        } catch (error) {
            console.error('❌ Error seeding admin user:', error);
        }
    }

    async authenticateUser(username, password) {
        const db = getDb();
        
        try {
            const [rows] = await db.execute('SELECT * FROM users WHERE username = ?', [username]);
            const user = rows[0];

            if (!user) {
                return null;
            }

            const isValid = await bcrypt.compare(password, user.password_hash);
            if (isValid) {
                // Update last login
                await db.execute('UPDATE users SET last_login = NOW() WHERE id = ?', [user.id]);
                
                return {
                    id: user.id,
                    username: user.username,
                    email: user.email,
                    role: user.role
                };
            }
            
            return null;
        } catch (error) {
            throw error;
        }
    }

    async changePassword(userId, newPassword) {
        const db = getDb();
        const passwordHash = await bcrypt.hash(newPassword, 10);
        
        const [result] = await db.execute(
            'UPDATE users SET password_hash = ? WHERE id = ?',
            [passwordHash, userId]
        );
        
        return result.affectedRows > 0;
    }

    async createUser(username, password, email, role = 'admin') {
        const db = getDb();
        const passwordHash = await bcrypt.hash(password, 10);
        
        const [result] = await db.execute(
            'INSERT INTO users (username, password_hash, email, role) VALUES (?, ?, ?, ?)',
            [username, passwordHash, email, role]
        );
        
        return result.insertId;
    }

    async getUserById(userId) {
        const db = getDb();
        const [rows] = await db.execute(
            'SELECT id, username, email, role, created_at, last_login FROM users WHERE id = ?',
            [userId]
        );
        return rows[0] || null;
    }
}

module.exports = new AuthService();
