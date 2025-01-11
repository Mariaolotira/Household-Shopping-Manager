<?php
$host = 'localhost'; // Database host
$dbname = 'housemanager'; // Database name
$username = 'root'; 
$password = ''; // Database password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username , $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Database Connection Error: " . $e->getMessage());
    die("Sorry, there was a problem connecting to the database.");
}
?>