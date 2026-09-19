<?php
$host = "localhost";
$user = "root";   // change if you have a different MySQL user
$pass = "";       // add password if you set one
$db   = "maintenance_db";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>

