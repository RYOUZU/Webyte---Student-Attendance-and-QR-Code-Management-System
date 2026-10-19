<?php
// Kunin ang database credentials galing sa Railway environment variables
$host = getenv('MYSQLHOST');
$user = getenv('MYSQLUSER');
$pass = getenv('MYSQLPASSWORD');
$db   = getenv('MYSQLDATABASE');
$port = getenv('MYSQLPORT');

// Koneksyon gamit ang MySQLi
$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Basahin ang webyte.sql file
$sqlFile = 'webyte.sql';
if (!file_exists($sqlFile)) {
    die("Error: webyte.sql was not found in the project folder.");
}

$sqlContent = file_get_contents($sqlFile);

// I-execute ang multi-query import
if ($conn->multi_query($sqlContent)) {
    do {
        // I-clear ang mga results
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());
    echo "Success! The database was imported successfully.";
} else {
    echo "Database import error: " . $conn->error;
}

$conn->close();
?>
