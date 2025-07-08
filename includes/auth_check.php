<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure functions.php is included
require_once __DIR__ . '/functions.php';

// Define get_user_role function directly in auth_check.php as a fallback
if (!function_exists('get_user_role')) {
    function get_user_role() {
        return isset($_SESSION['role']) ? $_SESSION['role'] : null;
    }
}

function check_auth($allowed_roles = null) {
    if (!is_logged_in()) {
        return false;
    }
    
    if ($allowed_roles === null) {
        return true;
    }
    
    if (!is_array($allowed_roles)) {
        $allowed_roles = array($allowed_roles);
    }
    
    $user_role = get_user_role();
    return in_array($user_role, $allowed_roles);
}

function require_auth($allowed_roles = null, $redirect_url = '/login.php') {
    if (!check_auth($allowed_roles)) {
        $_SESSION['error'] = "You don't have permission to access this page.";
        header("Location: $redirect_url");
        exit;
    }
}

$current_script = basename($_SERVER['SCRIPT_NAME']);

$access_rules = array(
    'admin/dashboard.php' => array('admin'),
    'admin/manage_users.php' => array('admin'),
    'admin/manage_agents.php' => array('admin'),
    'admin/view_reports.php' => array('admin'),
    'admin/assign_report.php' => array('admin'),
    'admin/edit_report.php' => array('admin'),
    'admin/delete_report.php' => array('admin'),
    'admin/export_csv.php' => array('admin'),
    'admin/edit_profile.php' => array('admin'),
    
    'agent/dashboard.php' => array('agent'),
    'agent/view_assigned.php' => array('agent'),
    'agent/update_status.php' => array('agent'),
    'agent/edit_profile.php' => array('agent'),
    
    'user/dashboard.php' => array('user'),
    'user/submit_report.php' => array('user'),
    'user/view_my_reports.php' => array('user'),
    'user/edit_profile.php' => array('user'),
    
    'edit_profile.php' => array('user', 'admin', 'agent'),
    
    'index.php' => null,
    'login.php' => null,
    'logout.php' => null,
    'register.php' => null,
    'report.php' => null
);

$script_path = '';

$uri_parts = explode('/', $_SERVER['REQUEST_URI']);
if (count($uri_parts) > 2) {
    $script_path = $uri_parts[1] . '/';
}

$script_path .= $current_script;

if (array_key_exists($script_path, $access_rules)) {
    $allowed_roles = $access_rules[$script_path];
    
    if ($allowed_roles !== null) {
        require_auth($allowed_roles);
    }
}
?>
