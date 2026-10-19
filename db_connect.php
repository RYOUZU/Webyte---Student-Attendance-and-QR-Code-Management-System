<?php
$host = getenv('MYSQLHOST') ?: "localhost";
$user = getenv('MYSQLUSER') ?: "root";
$password = getenv('MYSQLPASSWORD') ?: "";
$dbname = getenv('MYSQLDATABASE') ?: "webyte";
$port = getenv('MYSQLPORT') ?: 3306;

// Gumamit ng MySQLi connection para sumakto sa mga query sa signup.php
$conn = new mysqli($host, $user, $password, $dbname, $port);

// Suriin kung nagtagumpay ang koneksyon
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
?>
