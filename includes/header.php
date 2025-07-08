<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('is_logged_in')) {
    require_once dirname(__FILE__) . '/functions.php';
}

// Determine if we're in a dashboard page
$current_dir = basename(dirname($_SERVER['SCRIPT_NAME']));
$is_dashboard = in_array($current_dir, ['admin', 'agent', 'user']);

// Determine if we're in a subdirectory
$in_subdirectory = in_array($current_dir, ['admin', 'agent', 'user']);
$root_path = $in_subdirectory ? '../' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crime Reporting System</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #34495e;
            --accent-color: #3498db;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
            --sidebar-width: 260px;
            --sidebar-collapsed-width: 70px;
            --header-height: 60px;
            --transition-speed: 0.3s;
        }
        
        html, body {
            height: 100%;
            margin: 0;
        }
        
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background-color: #f4f6f9;
        }
        
        /* Sidebar Styles - Only for dashboard pages */
        .sidebar-wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: var(--sidebar-width);
            height: 100%;
            position: fixed;
            top: 0;
            left: 0;
            background-color: var(--primary-color);
            color: white;
            z-index: 1000;
            transition: all var(--transition-speed) ease;
            overflow-y: auto;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        
        .sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
        }
        
        .sidebar-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-logo {
            display: flex;
            align-items: center;
            color: white;
            text-decoration: none;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        .sidebar-logo i {
            margin-right: 10px;
            font-size: 1.5rem;
        }
        
        .sidebar.collapsed .sidebar-logo span {
            display: none;
        }
        
        .sidebar-toggle {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            font-size: 1.2rem;
        }
        
        .sidebar-profile {
            padding: 20px 15px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .profile-image {
            width: 80px;
            height: 80px;
            margin: 0 auto 10px;
            border-radius: 50%;
            overflow: hidden;
            border: 3px solid rgba(255, 255, 255, 0.2);
        }
        
        .profile-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .sidebar.collapsed .profile-info {
            display: none;
        }
        
        .sidebar.collapsed .profile-image {
            width: 40px;
            height: 40px;
        }
        
        .profile-info h5 {
            margin-bottom: 5px;
            font-size: 1rem;
        }
        
        .menu-header {
            font-size: 0.8rem;
            text-transform: uppercase;
            padding: 15px 15px 5px;
            color: rgba(255, 255, 255, 0.5);
            font-weight: bold;
        }
        
        .sidebar.collapsed .menu-header {
            display: none;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .sidebar-menu li {
            margin-bottom: 2px;
        }
        
        .sidebar-menu li a {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .sidebar-menu li a:hover, .sidebar-menu li.active a {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .sidebar-menu li a i {
            margin-right: 10px;
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
        }
        
        .sidebar.collapsed .sidebar-menu li a span {
            display: none;
        }
        
        /* Content Wrapper for dashboard */
        .content-wrapper {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 20px;
            transition: margin var(--transition-speed) ease;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .sidebar.collapsed ~ .content-wrapper {
            margin-left: var(--sidebar-collapsed-width);
        }
        
        /* Regular layout for non-dashboard pages */
        .regular-layout {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        .regular-content {
            flex: 1;
            padding: 20px;
            padding-bottom: 60px; /* Add extra padding at the bottom to prevent content from being hidden by footer */
        }
        
        /* Navbar for regular pages */
        .navbar-custom {
            background-color: var(--primary-color);
            padding: 15px 0;
        }
        
        .navbar-custom .navbar-brand {
            color: white;
            font-weight: bold;
        }
        
        .navbar-custom .nav-link {
            color: rgba(255, 255, 255, 0.8);
        }
        
        .navbar-custom .nav-link:hover,
        .navbar-custom .nav-link.active {
            color: white;
        }
        
        /* Footer */
        .footer {
            background-color: var(--primary-color);
            color: white;
            text-align: center;
            padding: 15px;
            margin-top: auto;
            position: relative;
            z-index: 10;
        }
        
        /* Responsive Styles */
        @media (max-width: 768px) {
            .sidebar {
                left: -var(--sidebar-width);
            }
            
            .sidebar.mobile-open {
                left: 0;
            }
            
            .content-wrapper {
                margin-left: 0;
            }
            
            .sidebar.collapsed {
                left: -var(--sidebar-collapsed-width);
            }
            
            .sidebar.collapsed.mobile-open {
                left: 0;
                width: var(--sidebar-width);
            }
            
            .sidebar.collapsed.mobile-open .sidebar-logo span,
            .sidebar.collapsed.mobile-open .profile-info,
            .sidebar.collapsed.mobile-open .menu-header,
            .sidebar.collapsed.mobile-open .sidebar-menu li a span {
                display: block;
            }
            
            .mobile-sidebar-toggle {
                display: block;
                position: fixed;
                top: 10px;
                left: 10px;
                z-index: 1001;
                background-color: var(--primary-color);
                color: white;
                border: none;
                border-radius: 4px;
                padding: 8px 12px;
                cursor: pointer;
            }
        }
        
        /* Other Styles */
        .bg-custom-primary { background-color: var(--primary-color) !important; color: white !important; }
        .bg-custom-secondary { background-color: var(--secondary-color) !important; color: white !important; }
        .bg-custom-accent { background-color: var(--accent-color) !important; color: white !important; }
        .bg-custom-success { background-color: var(--success-color) !important; color: white !important; }
        .bg-custom-warning { background-color: var(--warning-color) !important; color: white !important; }
        .bg-custom-danger { background-color: var(--danger-color) !important; color: white !important; }
        
        .btn-custom-primary { background-color: var(--primary-color); color: white; }
        .btn-custom-primary:hover { background-color: #1a252f; color: white; }
        
        .btn-custom-accent { background-color: var(--accent-color); color: white; }
        .btn-custom-accent:hover { background-color: #2980b9; color: white; }
        
        .btn-custom-success { background-color: var(--success-color); color: white; }
        .btn-custom-success:hover { background-color: #219653; color: white; }
        
        .hero-section {
            background-image: url('https://source.unsplash.com/1920x600/?crime,police');
            background-size: cover;
            background-position: center;
            color: white;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.7);
            padding: 100px 0;
            position: relative;
            margin-bottom: 50px;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.6);
        }
        
        .hero-content {
            position: relative;
            z-index: 1;
        }
        
        .hero-overlay {
            background-color: rgba(0,0,0,0.5);
            padding: 30px;
            border-radius: 10px;
        }
        
        .card {
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            border: none;
        }
        
        .card-header {
            border-radius: 8px 8px 0 0 !important;
            font-weight: 600;
        }
    </style>
</head>
<body>
<?php if ($is_dashboard): ?>
    <!-- Include sidebar for dashboard pages -->
    <?php include_once(dirname(__FILE__) . '/sidebar.php'); ?>
<?php else: ?>
    <!-- Regular Layout with Navbar for public pages -->
    <div class="regular-layout">
        <!-- Navbar -->
        <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
            <div class="container">
                <a class="navbar-brand" href="<?php echo $root_path; ?>index.php">
                    <i class="fas fa-shield-alt"></i> Crime Reporting System
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['SCRIPT_NAME']) == 'index.php' ? 'active' : ''; ?>" href="<?php echo $root_path; ?>index.php">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['SCRIPT_NAME']) == 'report.php' ? 'active' : ''; ?>" href="<?php echo $root_path; ?>report.php">Report Crime</a>
                        </li>
                    </ul>
                    
                    <ul class="navbar-nav">
                        <?php if (is_logged_in()): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <?php echo htmlspecialchars($_SESSION['username']); ?>
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                                    <?php if (has_role('admin')): ?>
                                        <li><a class="dropdown-item" href="<?php echo $root_path; ?>admin/dashboard.php">Admin Dashboard</a></li>
                                    <?php elseif (has_role('agent')): ?>
                                        <li><a class="dropdown-item" href="<?php echo $root_path; ?>agent/dashboard.php">Agent Dashboard</a></li>
                                    <?php else: ?>
                                        <li><a class="dropdown-item" href="<?php echo $root_path; ?>user/dashboard.php">User Dashboard</a></li>
                                    <?php endif; ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="<?php echo $root_path; ?>logout.php">Logout</a></li>
                                </ul>
                            </li>
                        <?php else: ?>
                            <li class="nav-item">
                                <a class="nav-link <?php echo basename($_SERVER['SCRIPT_NAME']) == 'login.php' ? 'active' : ''; ?>" href="<?php echo $root_path; ?>login.php">Login</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo basename($_SERVER['SCRIPT_NAME']) == 'register.php' ? 'active' : ''; ?>" href="<?php echo $root_path; ?>register.php">Register</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </nav>
        
        <!-- Regular Content -->
        <div class="regular-content">
            <?php
            if (isset($_SESSION['error'])) {
                echo '<div class="alert alert-danger" role="alert">' . $_SESSION['error'] . '</div>';
                unset($_SESSION['error']);
            }
            
            if (isset($_SESSION['success'])) {
                echo '<div class="alert alert-success" role="alert">' . $_SESSION['success'] . '</div>';
                unset($_SESSION['success']);
            }
            ?>
<?php endif; ?>
