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
      DB_TYPE: 'mysql',
      DB_HOST: '127.0.0.1',
      DB_PORT: '3306',
      DB_NAME: 'u402548537_gateway',
      DB_USER: 'u402548537_gateway',
      DB_PASSWORD: 'JustOmer2024$',
      BASE_URL: 'https://internationalitpro.com'
    }
  }]
};
