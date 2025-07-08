<?php
/**
 * Manage Agents
 * Admin page for managing system agents
 * Author: Crime Reporting System
 * Date: 2023-05-07
 */

// Include auth check
require_once '../includes/auth_check.php';

// Include database connection
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Ensure user is admin
if (!has_role('admin')) {
    $_SESSION['error'] = "You don't have permission to access this page.";
    header("Location: ../login.php");
    exit;
}

// Process agent actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action == 'add_agent') {
            $user_id = (int)$_POST['user_id'];
            $badge_number = sanitize_input($_POST['badge_number']);
            $department = sanitize_input($_POST['department']);
            $rank = sanitize_input($_POST['rank']);
            
            // Check if user exists and is not already an agent
            $sql = "SELECT * FROM users WHERE user_id = ? AND role != 'agent'";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 0) {
                $_SESSION['error'] = "Invalid user or user is already an agent.";
            } else {
                // Check if badge number is unique
                $sql = "SELECT agent_id FROM agents WHERE badge_number = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("s", $badge_number);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $_SESSION['error'] = "Badge number already exists.";
                } else {
                    // Begin transaction
                    $conn->begin_transaction();
                    
                    try {
                        // Update user role
                        $sql = "UPDATE users SET role = 'agent' WHERE user_id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                        
                        // Create agent record - FIX: Added backticks around `rank`
                        $sql = "INSERT INTO agents (user_id, badge_number, department, `rank`) VALUES (?, ?, ?, ?)";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("isss", $user_id, $badge_number, $department, $rank);
                        $stmt->execute();
                        
                        // Commit transaction
                        $conn->commit();
                        
                        $_SESSION['success'] = "Agent added successfully.";
                    } catch (Exception $e) {
                        // Rollback transaction on error
                        $conn->rollback();
                        $_SESSION['error'] = "Failed to add agent: " . $e->getMessage();
                    }
                }
            }
        } elseif ($action == 'update_agent') {
            $agent_id = (int)$_POST['agent_id'];
            $badge_number = sanitize_input($_POST['badge_number']);
            $department = sanitize_input($_POST['department']);
            $rank = sanitize_input($_POST['rank']);
            $status = sanitize_input($_POST['status']);
            
            // Check if badge number is unique (except for this agent)
            $sql = "SELECT agent_id FROM agents WHERE badge_number = ? AND agent_id != ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $badge_number, $agent_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $_SESSION['error'] = "Badge number already exists.";
            } else {
                // Update agent record - FIX: Added backticks around `rank`
                $sql = "UPDATE agents SET badge_number = ?, department = ?, `rank` = ?, status = ? WHERE agent_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssi", $badge_number, $department, $rank, $status, $agent_id);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = "Agent updated successfully.";
                } else {
                    $_SESSION['error'] = "Failed to update agent.";
                }
            }
        } elseif ($action == 'delete_agent') {
            $agent_id = (int)$_POST['agent_id'];
            $user_id = (int)$_POST['user_id'];
            
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // Delete agent record
                $sql = "DELETE FROM agents WHERE agent_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $agent_id);
                $stmt->execute();
                
                // Update user role back to regular user
                $sql = "UPDATE users SET role = 'user' WHERE user_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                
                // Commit transaction
                $conn->commit();
                
                $_SESSION['success'] = "Agent removed successfully.";
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                $_SESSION['error'] = "Failed to remove agent: " . $e->getMessage();
            }
        }
    }
}

// Get search parameters
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';

// Build query for agents
$sql = "SELECT a.*, u.username, u.full_name, u.email, u.phone
        FROM agents a
        JOIN users u ON a.user_id = u.user_id
        WHERE 1=1";
$params = array();
$types = "";

if (!empty($search)) {
    $sql .= " AND (u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ? OR a.badge_number LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ssss";
}

if (!empty($status_filter)) {
    $sql .= " AND a.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$sql .= " ORDER BY a.agent_id DESC";

// Prepare and execute query
$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$agents_result = $stmt->get_result();

// Get users who are not agents for the add agent form
$sql = "SELECT u.user_id, u.username, u.full_name, u.email
        FROM users u
        LEFT JOIN agents a ON u.user_id = a.user_id
        WHERE a.agent_id IS NULL AND u.role != 'agent'
        ORDER BY u.full_name";
$non_agent_users = $conn->query($sql);

// Include header
include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Manage Agents</h2>
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
                    <input type="text" class="form-control" name="search" placeholder="Search by name, email or badge number" value="<?php echo htmlspecialchars($search); ?>">
                    <button class="btn btn-primary" type="submit">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>
            </div>
            <div class="col-md-4">
                <select name="status" class="form-select" onchange="this.form.submit()">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-2">
                <a href="manage_agents.php" class="btn btn-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Add Agent Button -->
<div class="mb-4">
    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addAgentModal">
        <i class="fas fa-plus me-2"></i>Add New Agent
    </button>
</div>

<!-- Agents Table -->
<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Agents</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Badge Number</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Rank</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($agents_result && $agents_result->num_rows > 0): ?>
                        <?php while ($agent = $agents_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $agent['agent_id']; ?></td>
                                <td><?php echo htmlspecialchars($agent['badge_number']); ?></td>
                                <td><?php echo htmlspecialchars($agent['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($agent['department']); ?></td>
                                <td><?php echo htmlspecialchars($agent['rank']); ?></td>
                                <td>
                                    <small>
                                        <div><i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($agent['email']); ?></div>
                                        <?php if (!empty($agent['phone'])): ?>
                                            <div><i class="fas fa-phone me-1"></i> <?php echo htmlspecialchars($agent['phone']); ?></div>
                                        <?php endif; ?>
                                    </small>
                                </td>
                                <td>
                                    <span class="badge <?php echo $agent['status'] == 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                        <?php echo ucfirst(htmlspecialchars($agent['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                            Actions
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#editAgentModal<?php echo $agent['agent_id']; ?>">
                                                    <i class="fas fa-edit me-2"></i>Edit
                                                </button>
                                            </li>
                                            <li>
                                                <button type="button" class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#deleteAgentModal<?php echo $agent['agent_id']; ?>">
                                                    <i class="fas fa-trash-alt me-2"></i>Remove Agent Status
                                                </button>
                                            </li>
                                        </ul>
                                    </div>
                                    
                                    <!-- Edit Agent Modal -->
                                    <div class="modal fade" id="editAgentModal<?php echo $agent['agent_id']; ?>" tabindex="-1" aria-labelledby="editAgentModalLabel<?php echo $agent['agent_id']; ?>" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="editAgentModalLabel<?php echo $agent['agent_id']; ?>">Edit Agent</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form method="post" action="">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="action" value="update_agent">
                                                        <input type="hidden" name="agent_id" value="<?php echo $agent['agent_id']; ?>">
                                                        
                                                        <div class="mb-3">
                                                            <label for="badge_number<?php echo $agent['agent_id']; ?>" class="form-label">Badge Number</label>
                                                            <input type="text" class="form-control" id="badge_number<?php echo $agent['agent_id']; ?>" name="badge_number" value="<?php echo htmlspecialchars($agent['badge_number']); ?>" required>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label for="department<?php echo $agent['agent_id']; ?>" class="form-label">Department</label>
                                                            <input type="text" class="form-control" id="department<?php echo $agent['agent_id']; ?>" name="department" value="<?php echo htmlspecialchars($agent['department']); ?>" required>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label for="rank<?php echo $agent['agent_id']; ?>" class="form-label">Rank</label>
                                                            <input type="text" class="form-control" id="rank<?php echo $agent['agent_id']; ?>" name="rank" value="<?php echo htmlspecialchars($agent['rank']); ?>">
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label for="status<?php echo $agent['agent_id']; ?>" class="form-label">Status</label>
                                                            <select class="form-select" id="status<?php echo $agent['agent_id']; ?>" name="status">
                                                                <option value="active" <?php echo $agent['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                                                <option value="inactive" <?php echo $agent['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
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
                                    
                                    <!-- Delete Agent Modal -->
                                    <div class="modal fade" id="deleteAgentModal<?php echo $agent['agent_id']; ?>" tabindex="-1" aria-labelledby="deleteAgentModalLabel<?php echo $agent['agent_id']; ?>" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="deleteAgentModalLabel<?php echo $agent['agent_id']; ?>">Remove Agent Status</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>Are you sure you want to remove agent status from <strong><?php echo htmlspecialchars($agent['full_name']); ?></strong>?</p>
                                                    <p>This will convert the user back to a regular user and remove all agent privileges.</p>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <form method="post" action="">
                                                        <input type="hidden" name="action" value="delete_agent">
                                                        <input type="hidden" name="agent_id" value="<?php echo $agent['agent_id']; ?>">
                                                        <input type="hidden" name="user_id" value="<?php echo $agent['user_id']; ?>">
                                                        <button type="submit" class="btn btn-danger">Remove Agent Status</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center">No agents found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Agent Modal -->
<div class="modal fade" id="addAgentModal" tabindex="-1" aria-labelledby="addAgentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addAgentModalLabel">Add New Agent</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_agent">
                    
                    <div class="mb-3">
                        <label for="user_id" class="form-label">Select User</label>
                        <select class="form-select" id="user_id" name="user_id" required>
                            <option value="">Select User</option>
                            <?php if ($non_agent_users && $non_agent_users->num_rows > 0): ?>
                                <?php while ($user = $non_agent_users->fetch_assoc()): ?>
                                    <option value="<?php echo $user['user_id']; ?>">
                                        <?php echo htmlspecialchars($user['full_name'] . ' (' . $user['username'] . ')'); ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="badge_number" class="form-label">Badge Number</label>
                        <input type="text" class="form-control" id="badge_number" name="badge_number" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="department" class="form-label">Department</label>
                        <input type="text" class="form-control" id="department" name="department" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="rank" class="form-label">Rank</label>
                        <input type="text" class="form-control" id="rank" name="rank">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Add Agent</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Include footer
include '../includes/footer.php';
?>
