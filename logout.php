<?php
/**
 * Logout Page
 * Author: Crime Reporting System
 * Date: 2023-05-07
 * 
 * This file handles user logout
 */

// Start session
session_start();

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: login.php");
exit;
?>
