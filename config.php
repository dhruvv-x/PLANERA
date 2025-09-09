<?php
// config.php - update with your DB credentials & port
define('DB_HOST','localhost');   // use 127.0.0.1 to avoid IPv6/host-name resolution quirks
define('DB_PORT', 3307);         // <--- set your MySQL port here
define('DB_USER','root');
define('DB_PASS','');            // put your MySQL password here if any
define('DB_NAME','smart_scheduler');

function db_connect(){
    // mysqli::__construct(host, user, password, dbname, port, socket)
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    if($conn->connect_error){
        // Show readable error for debugging (remove or hide in production)
        die('DB Connect Error: ' . $conn->connect_error . ' (Errno: ' . $conn->connect_errno . ')');
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}
?>
