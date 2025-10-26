require('dotenv').config();
const express = require('express');
const cors = require('cors');
const bodyParser = require('body-parser');
const session = require('express-session');
const path = require('path');
// Use MySQL if DB_TYPE is set to mysql, otherwise use SQLite
const database = process.env.DB_TYPE === 'mysql' 
  ? require('./services/database-mysql') 
  : require('./services/database');
const authService = process.env.DB_TYPE === 'mysql'
  ? require('./services/auth-mysql')
  : require('./services/auth');

// Initialize Express app
const app = express();
const PORT = process.env.PORT || 3000;

// Middleware
app.use(cors());

// Session configuration
app.use(session({
  secret: process.env.SESSION_SECRET || 'international-pro-payment-gateway-secret-2025',
  resave: false,
  saveUninitialized: false,
  cookie: {
    secure: false, // Set to true only when using HTTPS
    httpOnly: true,
    maxAge: 24 * 60 * 60 * 1000 // 24 hours
  }
}));

// IMPORTANT: Webhook route must be before bodyParser for raw body
const webhookRoutes = require('./routes/webhook');
app.use('/api', webhookRoutes);

// Now apply body parser for other routes
app.use(bodyParser.json());
app.use(bodyParser.urlencoded({ extended: true }));

// Serve static files (frontend)
app.use(express.static(path.join(__dirname, '../frontend')));
app.use(express.static(path.join(__dirname, '../public')));

// Import routes
const paymentRoutes = require('./routes/payment');
const syncRoutes = require('./routes/sync');
const authRoutes = require('./routes/auth');
const { requireAuth, redirectIfAuthenticated } = require('./middleware/auth');

// Routes
app.use('/api/auth', authRoutes);
app.use('/api', requireAuth, paymentRoutes);
app.use('/api', requireAuth, syncRoutes);

// Health check endpoint
app.get('/api/health', (req, res) => {
  res.json({
    status: 'ok',
    message: 'Payment Gateway API is running',
    timestamp: new Date().toISOString(),
    environment: process.env.NODE_ENV || 'development'
  });
});

// Serve frontend pages
app.get('/', (req, res) => {
  res.sendFile(path.join(__dirname, '../frontend/index.html'));
});

app.get('/login', redirectIfAuthenticated, (req, res) => {
  res.sendFile(path.join(__dirname, '../frontend/login.html'));
});

app.get('/admin', requireAuth, (req, res) => {
  res.sendFile(path.join(__dirname, '../frontend/admin.html'));
});

app.get('/pay/:payment_link_id', (req, res) => {
  res.sendFile(path.join(__dirname, '../frontend/pay.html'));
});

app.get('/success.html', (req, res) => {
  res.sendFile(path.join(__dirname, '../frontend/success.html'));
});

app.get('/error.html', (req, res) => {
  res.sendFile(path.join(__dirname, '../frontend/error.html'));
});

// 404 handler
app.use((req, res) => {
  res.status(404).json({
    error: 'Not Found',
    message: 'The requested resource was not found'
  });
});

// Error handler
app.use((err, req, res, next) => {
  console.error('Error:', err);
  res.status(500).json({
    error: 'Internal Server Error',
    message: process.env.NODE_ENV === 'development' ? err.message : 'Something went wrong'
  });
});

// Initialize database and start server
async function startServer() {
  try {
    // Initialize database
    await database.init();
    
    // Initialize auth service
    await authService.initialize();
    
    // Start server
    app.listen(PORT, () => {
      console.log('═══════════════════════════════════════════════');
      console.log('🚀 Payment Gateway Server Started!');
      console.log('═══════════════════════════════════════════════');
      console.log(`📡 Server running on: http://localhost:${PORT}`);
      console.log(`🌍 Environment: ${process.env.NODE_ENV || 'development'}`);
      console.log(`⏰ Started at: ${new Date().toLocaleString()}`);
      console.log('═══════════════════════════════════════════════');
      console.log('\n📋 API Endpoints:');
      console.log(`   GET  /api/health - Health check`);
      console.log(`   POST /api/create-payment-link - Create payment link`);
      console.log(`   POST /api/create-payment-intent - Create Stripe intent`);
      console.log(`   GET  /api/payment/:id - Get payment details`);
      console.log(`   GET  /api/payments - List all payments`);
      console.log(`   POST /api/webhook - Stripe webhook`);
      console.log('\n🌐 Frontend Pages:');
      console.log(`   GET  / - Home page`);
      console.log(`   GET  /admin - Admin dashboard`);
      console.log(`   GET  /pay/:id - Payment page`);
      console.log('\n💡 Tip: Make sure to configure your .env file!');
      console.log('═══════════════════════════════════════════════\n');
    });
  } catch (error) {
    console.error('❌ Failed to start server:', error);
    process.exit(1);
  }
}

// Handle graceful shutdown
process.on('SIGINT', async () => {
  console.log('\n⏹️  Shutting down gracefully...');
  await database.close();
  process.exit(0);
});

// Start the server
startServer();

module.exports = app;
