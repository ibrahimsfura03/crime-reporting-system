<?php
require_once '../includes/auth_check.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Ensure user is admin
if (!has_role('admin')) {
    $_SESSION['error'] = "You don't have permission to access this page.";
    header("Location: ../login.php");
    exit;
}

// Process user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action  = $_POST['action'];
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;

    if ($action === 'delete' && $user_id > 0) {
        if ($user_id == $_SESSION['user_id']) {
            $_SESSION['error'] = "You cannot delete your own account.";
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $_SESSION[$stmt->execute() ? 'success' : 'error'] = $stmt->execute()
                ? "User deleted successfully."
                : "Failed to delete user.";
            $stmt->close();
        }
    } elseif ($action === 'change_role' && $user_id > 0) {
        $new_role = sanitize_input($_POST['new_role']);

        if ($user_id == $_SESSION['user_id'] && $new_role !== 'admin') {
            $_SESSION['error'] = "You cannot change your own admin role.";
        } else {
            // Begin transaction
            $conn->begin_transaction();
            try {
                // Update users table
                $stmt = $conn->prepare("UPDATE users SET role = ? WHERE user_id = ?");
                $stmt->bind_param("si", $new_role, $user_id);
                $stmt->execute();
                $stmt->close();

                // If new role is agent, ensure an agents record exists
                if ($new_role === 'agent') {
                    $stmt = $conn->prepare("SELECT agent_id FROM agents WHERE user_id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $stmt->close();

                    if ($result->num_rows === 0) {
                        $badge_number = 'A' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
                        $department   = 'General Investigation';
                        $stmt = $conn->prepare("INSERT INTO agents (user_id, badge_number, department) VALUES (?, ?, ?)");
                        $stmt->bind_param("iss", $user_id, $badge_number, $department);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
                // Commit transaction
                $conn->commit();
                $_SESSION['success'] = "User role updated successfully.";
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                $_SESSION['error'] = "Failed to update user role: " . $e->getMessage();
            }
        }
    }
    header("Location: manage_users.php"); // Refresh page after action
    exit;
}

// Get search parameters
$search       = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$role_filter  = isset($_GET['role'])   ? sanitize_input($_GET['role'])   : '';

// Build query
$sql = "SELECT * FROM users WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $sql .= " AND (username LIKE ? OR email LIKE ? OR full_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if (!empty($role_filter)) {
    $sql .= " AND role = ?";
    $params[] = $role_filter;
    $types .= "s";
}

$sql .= " ORDER BY user_id DESC";

// Prepare and execute query
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

if (!$stmt->execute()) {
    die("Execute failed: " . $stmt->error);
}

$result = $stmt->get_result();
if ($result === false) {
    die("Get result failed: " . $conn->error);
}

// Fetch all users into an array
$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

// Include header (HTML begins)
include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Manage Users</h2>
    <a href="dashboard.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
    </a>
</div>

<!-- Search and Filter -->
<div class="card mb-4">
    <div class="card-body">
        <form method="get" action="" class="row g-3">
            <div class="col-md-6">
                <div class="input-group">
                    <input type="text" class="form-control" name="search" placeholder="Search by username, email or name" value="<?php echo htmlspecialchars($search); ?>">
                    <button class="btn btn-primary" type="submit">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>
            </div>
            <div class="col-md-4">
                <select name="role" class="form-select" onchange="this.form.submit()">
                    <option value="">All Roles</option>
                    <option value="admin" <?php echo $role_filter == 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="agent" <?php echo $role_filter == 'agent' ? 'selected' : ''; ?>>Agent</option>
                    <option value="user" <?php echo $role_filter == 'user' ? 'selected' : ''; ?>>User</option>
                </select>
            </div>
            <div class="col-md-2">
                <a href="manage_users.php" class="btn btn-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Users Table -->
<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Users</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($users)): ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['user_id']; ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['full_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="badge <?php 
                                        echo $user['role'] == 'admin' ? 'bg-danger' : 
                                            ($user['role'] == 'agent' ? 'bg-warning' : 'bg-info'); 
                                    ?>">
                                        <?php echo ucfirst(htmlspecialchars($user['role'])); ?>
                                    </span>
                                </td>
                                <td><?php echo format_date($user['created_at']); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                            Actions
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#changeRoleModal<?php echo $user['user_id']; ?>">
                                                    <i class="fas fa-user-tag me-2"></i>Change Role
                                                </button>
                                            </li>
                                            <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                                <li>
                                                    <button type="button" class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $user['user_id']; ?>">
                                                        <i class="fas fa-trash-alt me-2"></i>Delete
                                                    </button>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                    
                                    <!-- Change Role Modal -->
                                    <div class="modal fade" id="changeRoleModal<?php echo $user['user_id']; ?>" tabindex="-1" aria-labelledby="changeRoleModalLabel<?php echo $user['user_id']; ?>" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="changeRoleModalLabel<?php echo $user['user_id']; ?>">Change Role for <?php echo htmlspecialchars($user['username']); ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form method="post" action="">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="action" value="change_role">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                        <div class="mb-3">
                                                            <label for="new_role<?php echo $user['user_id']; ?>" class="form-label">New Role</label>
                                                            <select class="form-select" id="new_role<?php echo $user['user_id']; ?>" name="new_role">
                                                                <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                                <option value="agent" <?php echo $user['role'] == 'agent' ? 'selected' : ''; ?>>Agent</option>
                                                                <option value="user" <?php echo $user['role'] == 'user' ? 'selected' : ''; ?>>User</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-primary">Save Changes</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Delete Modal -->
                                    <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                        <div class="modal fade" id="deleteModal<?php echo $user['user_id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?php echo $user['user_id']; ?>" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="deleteModalLabel<?php echo $user['user_id']; ?>">Delete User</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>Are you sure you want to delete the user <strong><?php echo htmlspecialchars($user['username']); ?></strong>?</p>
                                                        <p class="text-danger">This action cannot be undone.</p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <form method="post" action="">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                            <button type="submit" class="btn btn-danger">Delete</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">No users found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
// Close statement and connection
$stmt->close();
$conn->close();

// Include footer
include '../includes/footer.php';
?>