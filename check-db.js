const sqlite3 = require('sqlite3').verbose();
const path = require('path');

const dbPath = path.join(__dirname, 'backend/config/payments.db');
const db = new sqlite3.Database(dbPath);

db.all('SELECT * FROM payments', [], (err, rows) => {
  if (err) {
    console.error('Error:', err);
  } else {
    console.log('Total payments:', rows.length);
    console.log('Payments:', JSON.stringify(rows, null, 2));
  }
  db.close();
});
