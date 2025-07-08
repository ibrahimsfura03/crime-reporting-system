<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('is_logged_in')) {
    require_once dirname(__FILE__) . '/functions.php';
}

// Determine if we're in a subdirectory
$current_dir = basename(dirname($_SERVER['SCRIPT_NAME']));
$in_subdirectory = in_array($current_dir, ['admin', 'agent', 'user']);
$root_path = $in_subdirectory ? '../' : '';

// Get user data if logged in
$user_data = null;
if (is_logged_in()) {
    $user_id = $_SESSION['user_id'];
    $sql = "SELECT * FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
    }
    
    // Get agent data if user is an agent
    if (has_role('agent')) {
        $sql = "SELECT * FROM agents WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $agent_data = $result->fetch_assoc();
            $user_data['agent'] = $agent_data;
        }
    }
}

// Function to check if a menu item should be active
function is_active($page, $dir = '') {
    $current_page = basename($_SERVER['SCRIPT_NAME']);
    $current_dir = basename(dirname($_SERVER['SCRIPT_NAME']));
    
    if (!empty($dir) && $dir != $current_dir) {
        return false;
    }
    
    return $page == $current_page;
}
?>

<!-- Sidebar -->
<div class="sidebar-wrapper">
    <div id="sidebar" class="sidebar">
        <!-- Sidebar Header with Logo -->
        <div class="sidebar-header">
            <a href="<?php echo $root_path; ?>index.php" class="sidebar-logo">
                <i class="fas fa-shield-alt"></i>
                <span>Crime Reporting</span>
            </a>
            <button id="sidebarToggle" class="sidebar-toggle">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        
        <!-- User Profile Section -->
        <?php if (is_logged_in() && $user_data): ?>
            <div class="sidebar-profile">
                <div class="profile-image">
                    <img src="<?php echo $root_path; ?>uploads/profile/<?php echo !empty($user_data['profile_image']) ? $user_data['profile_image'] : 'default.jpg'; ?>" alt="Profile Image">
                </div>
                <div class="profile-info">
                    <h5><?php echo htmlspecialchars($user_data['full_name']); ?></h5>
                    <p class="badge <?php 
                        echo has_role('admin') ? 'bg-danger' : 
                            (has_role('agent') ? 'bg-warning' : 'bg-info'); 
                    ?>">
                        <?php echo ucfirst(htmlspecialchars($user_data['role'])); ?>
                    </p>
                    <?php if (has_role('agent') && isset($user_data['agent'])): ?>
                        <p class="badge bg-secondary"><?php echo htmlspecialchars($user_data['agent']['badge_number']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Sidebar Menu -->
        <ul class="sidebar-menu">
            <li class="menu-header">Main Navigation</li>
            
            <li class="<?php echo is_active('index.php') ? 'active' : ''; ?>">
                <a href="<?php echo $root_path; ?>index.php">
                    <i class="fas fa-home"></i>
                    <span>Home</span>
                </a>
            </li>
            
            <?php if (is_logged_in()): ?>
                <?php if (!has_role('admin') && !has_role('agent')): ?>
                    <!-- User Menu - Only show for regular users, not for admins or agents -->
                    <li class="menu-header">User Menu</li>
                    
                    <li class="<?php echo is_active('dashboard.php', 'user') ? 'active' : ''; ?>">
                        <a href="<?php echo $root_path; ?>user/dashboard.php">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>User Dashboard</span>
                        </a>
                    </li>
                    
                    <li class="<?php echo is_active('submit_report.php', 'user') ? 'active' : ''; ?>">
                        <a href="<?php echo $root_path; ?>user/submit_report.php">
                            <i class="fas fa-plus-circle"></i>
                            <span>Submit Report</span>
                        </a>
                    </li>
                    
                    <li class="<?php echo is_active('view_my_reports.php', 'user') ? 'active' : ''; ?>">
                        <a href="<?php echo $root_path; ?>user/view_my_reports.php">
                            <i class="fas fa-list"></i>
                            <span>My Reports</span>
                        </a>
                    </li>
                    
                    <li class="<?php echo is_active('edit_profile.php', 'user') ? 'active' : ''; ?>">
                        <a href="<?php echo $root_path; ?>user/edit_profile.php">
                            <i class="fas fa-user-edit"></i>
                            <span>Edit Profile</span>
                        </a>
                    </li>
                <?php endif; ?>
                
                <?php if (has_role('agent')): ?>
                    <!-- Agent Menu -->
                    <li class="menu-header">Agent Menu</li>
                    
                    <li class="<?php echo is_active('dashboard.php', 'agent') ? 'active' : ''; ?>">
                        <a href="<?php echo $root_path; ?>agent/dashboard.php">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Agent Dashboard</span>
                        </a>
                    </li>
                    
                    <li class="<?php echo is_active('view_assigned.php', 'agent') ? 'active' : ''; ?>">
                        <a href="<?php echo $root_path; ?>agent/view_assigned.php">
                            <i class="fas fa-tasks"></i>
                            <span>Assigned Cases</span>
                        </a>
                    </li>
                    
                    <li class="<?php echo is_active('update_status.php', 'agent') ? 'active' : ''; ?>">
                        <a href="<?php echo $root_path; ?>agent/update_status.php">
                            <i class="fas fa-edit"></i>
                            <span>Update Status</span>
                        </a>
                    </li>
                    
                    <li class="<?php echo is_active('edit_profile.php', 'agent') ? 'active' : ''; ?>">
                        <a href="<?php echo $root_path; ?>agent/edit_profile.php">
                            <i class="fas fa-user-edit"></i>
                            <span>Edit Agent Profile</span>
                        </a>
                    </li>
                <?php endif; ?>
                
                <?php if (has_role('admin')): ?>
                    <!-- Admin Menu -->
                    <li class="menu-header">Admin Menu</li>
                    
                    <li class="<?php echo is_active('dashboard.php', 'admin') ? 'active' : ''; ?>">
                        <a href="<?php echo $root_path; ?>admin/dashboard.php">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Admin Dashboard</span>
                        </a>
                    </li>
                    
                    <li class="<?php echo is_active('manage_users.php', 'admin') ? 'active' : ''; ?>">
                        <a href="<?php echo $root_path; ?>admin/manage_users.php">
                            <i class="fas fa-users"></i>
                            <span>Manage Users</span>
                        </a>
                    </li>
                    
                    <li class="<?php echo is_active('manage_agents.php', 'admin') ? 'active' : ''; ?>">
                        <a href="<?php echo $root_path; ?>admin/manage_agents.php">
                            <i class="fas fa-user-shield"></i>
                            <span>Manage Agents</span>
                        </a>
                    </li>
                    
                    <li class="<?php echo is_active('view_reports.php', 'admin') ? 'active' : ''; ?>">
                        <a href="<?php echo $root_path; ?>admin/view_reports.php">
                            <i class="fas fa-clipboard-list"></i>
                            <span>View Reports</span>
                        </a>
                    </li>
                    
                    <li class="<?php echo is_active('assign_report.php', 'admin') ? 'active' : ''; ?>">
                        <a href="<?php echo $root_path; ?>admin/assign_report.php">
                            <i class="fas fa-user-plus"></i>
                            <span>Assign Reports</span>
                        </a>
                    </li>
                    
                    <li class="<?php echo is_active('export_csv.php', 'admin') ? 'active' : ''; ?>">
                        <a href="<?php echo $root_path; ?>admin/export_csv.php">
                            <i class="fas fa-file-csv"></i>
                            <span>Export Data</span>
                        </a>
                    </li>
                    
                    <li class="<?php echo is_active('edit_profile.php', 'admin') ? 'active' : ''; ?>">
                        <a href="<?php echo $root_path; ?>admin/edit_profile.php">
                            <i class="fas fa-user-edit"></i>
                            <span>Edit Admin Profile</span>
                        </a>
                    </li>
                <?php endif; ?>
            <?php else: ?>
                <!-- Authentication Menu for Guests -->
                <li class="menu-header">Authentication</li>
                
                <li class="<?php echo is_active('login.php') ? 'active' : ''; ?>">
                    <a href="<?php echo $root_path; ?>login.php">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>Login</span>
                    </a>
                </li>
                
                <li class="<?php echo is_active('register.php') ? 'active' : ''; ?>">
                    <a href="<?php echo $root_path; ?>register.php">
                        <i class="fas fa-user-plus"></i>
                        <span>Register</span>
                    </a>
                </li>
            <?php endif; ?>
            
            <!-- Logout Option for Logged In Users -->
            <?php if (is_logged_in()): ?>
                <li class="menu-header">Account</li>
                
                <li>
                    <a href="<?php echo $root_path; ?>logout.php">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </div>

    
<!-- Mobile Sidebar Toggle Button -->
<button class="mobile-sidebar-toggle d-lg-none" id="mobileSidebarToggle">
    <i class="fas fa-bars"></i>
</button>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sidebar toggle functionality
    const sidebarToggle = document.getElementById('sidebarToggle');
    const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
    const sidebar = document.getElementById('sidebar');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            // Save state to localStorage
            localStorage.setItem('sidebar-collapsed', sidebar.classList.contains('collapsed'));
        });
    }
    
    if (mobileSidebarToggle) {
        mobileSidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('mobile-open');
        });
    }
    
    // Check for saved state
    const sidebarState = localStorage.getItem('sidebar-collapsed');
    if (sidebarState === 'true') {
        sidebar.classList.add('collapsed');
    }
    
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(event) {
        const isMobile = window.innerWidth < 992;
        if (isMobile && sidebar.classList.contains('mobile-open') && 
            !sidebar.contains(event.target) && 
            event.target !== mobileSidebarToggle) {
            sidebar.classList.remove('mobile-open');
        }
    });
});
</script>

    
    <!-- Dashboard Content -->
    <div class="content-wrapper">
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
