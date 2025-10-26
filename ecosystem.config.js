module.exports = {
  apps: [{
    name: 'payment-gateway',
    script: './backend/server.js',
    instances: 1,
    exec_mode: 'fork',
    autorestart: true,
    watch: false,
    max_memory_restart: '500M',
    env: {
      NODE_ENV: 'production',
      DB_TYPE: 'mysql'
    }
  }]
};
