<?php
$db_host = 'localhost:3307';
$db_name = 'crime_reporting_system';
$db_user = 'root';
$db_pass = '8569IBB';

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Set charset to ensure proper handling of special characters
    $conn->set_charset("utf8mb4");
    
 } catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
 }
 
 // Create uploads directory if it doesn't exist
 $upload_dirs = [
    '../uploads/profile/',
    '../uploads/evidence/',
    'uploads/profile/',
    'uploads/evidence/'
 ];
 
 foreach ($upload_dirs as $dir) {
    if (!file_exists($dir) && !is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
 }
 ?>