<?php
// Database configuration
$host = "localhost";       // Host name (XAMPP default is 'localhost')
$username = "root";        // Default username for XAMPP
$password = "";            // Default password is empty
$database = "team";  // Replace with your database name

// Create connection
$conn = mysqli_connect($host, $username, $password, $database);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

?>
