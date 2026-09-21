<?php
$host = getenv('DB_HOST') ?: "localhost";
$port = getenv('DB_PORT') ?: "3306";
$user = getenv('DB_USER') ?: "root";   // change if you have a different MySQL user
$pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : "";       // add password if you set one
$db   = getenv('DB_NAME') ?: "maintenance_db";

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>

