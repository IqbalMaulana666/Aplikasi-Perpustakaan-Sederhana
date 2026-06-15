<?php
// Database Configuration
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root'); //username databsase
define('DB_PASS', ''); //password databasee
define('DB_NAME', 'bukukita_smk');

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8");
} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}
