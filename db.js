const mysql = require('mysql2');
const connection = mysql.createConnection({
  host: 'localhost',
  user: 'root',
  password: '',
  database: 'your_database_name'
});

connection.connect((err) => {
  if (err) console.error('Database connection failed: ' + err.stack);
  else console.log('Connected to MySQL via Node.js!');
});