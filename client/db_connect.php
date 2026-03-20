<?php
// db_connect.php
$servername = "localhost";   // Database host (keep as localhost if MySQL is on same server)
$username   = "root";        // Database username
$password   = "";            // Database password (empty for XAMPP by default)
$dbname     = "lms_db";      // Database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
?>

