<?php
$host = $_ENV['DB_HOST'] ?? "localhost";
$user = $_ENV['DB_USER'] ?? "your_database_user";
$pass = $_ENV['DB_PASS'] ?? "your_database_password";
$dbname = $_ENV['DB_NAME'] ?? "your_database_name";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>