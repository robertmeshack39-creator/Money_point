<?php
// connection.php - MySQL Database Connection

// Database configuration
$host = 'localhost';
$port = '3306';
$dbname = 'mobile_money_db';
$username = 'root';
$password = '';

// Create connection
try {
    // PDO Connection (Recommended)
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Uncomment to test connection
    // echo "✅ Connected successfully!";
    
} catch (PDOException $e) {
    die("❌ Connection failed: " . $e->getMessage());
}

// MySQLi Connection (Alternative)
// $conn = new mysqli($host, $username, $password, $dbname, $port);
// 
// if ($conn->connect_error) {
//     die("❌ Connection failed: " . $conn->connect_error);
// }
// echo "✅ Connected successfully!";
?>