<?php
/**
 * Agent Dashboard
 * Author: Crime Reporting System
 * Date: 2023-05-07
 */

// Include auth check
require_once '../includes/auth_check.php';

// Include database connection
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Ensure user is agent
if (!has_role('agent')) {
    $_SESSION['error'] = "You don't have permission to access this page.";
    header("Location: ../login.php");
    exit;
}

// Get agent information
$sql = "SELECT a.* FROM agents a JOIN users u ON a.user_id = u.user_id WHERE u.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $_SESSION['error'] = "Agent profile not found.";
    header("Location: ../logout.php");
    exit;
}

$agent = $result->fetch_assoc();

// Get assigned cases count
$sql = "SELECT COUNT(*) as count FROM incident_agent WHERE agent_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $agent['agent_id']);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$assigned_count = $row['count'];

// Get open cases count
$sql = "SELECT COUNT(*) as count 
        FROM incident_agent ia 
        JOIN incidents i ON ia.incident_id = i.incident_id 
        JOIN status_codes sc ON i.status_id = sc.status_id 
        WHERE ia.agent_id = ? AND sc.name != 'Closed'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $agent['agent_id']);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$open_count = $row['count'];

// Get closed cases count
$sql = "SELECT COUNT(*) as count 
        FROM incident_agent ia 
        JOIN incidents i ON ia.incident_id = i.incident_id 
        JOIN status_codes sc ON i.status_id = sc.status_id 
        WHERE ia.agent_id = ? AND sc.name = 'Closed'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $agent['agent_id']);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$closed_count = $row['count'];

// Get recent assigned cases
$sql = "SELECT i.incident_id, i.title, i.incident_date, i.location, ct.name as crime_type, sc.name as status
        FROM incident_agent ia
        JOIN incidents i ON ia.incident_id = i.incident_id
        JOIN crime_types ct ON i.crime_type_id = ct.type_id
        JOIN status_codes sc ON i.status_id = sc.status_id
        WHERE ia.agent_id = ?
        ORDER BY ia.assigned_date DESC
        LIMIT 5";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $agent['agent_id']);
$stmt->execute();
$recent_cases = $stmt->get_result();

// Include header
include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2>Agent Dashboard</h2>
    </div>
</div>

<!-- Agent Profile -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Agent Profile</h5>
            </div>
            <div class="card-body text-center">
                <img src="https://via.placeholder.com/150" class="rounded-circle mb-3" alt="Agent Photo">
                <h4><?php echo htmlspecialchars($_SESSION['full_name']); ?></h4>
                <p class="text-muted"><?php echo htmlspecialchars($agent['badge_number']); ?> - <?php echo htmlspecialchars($agent['department']); ?></p>
                <?php if (!empty($agent['rank'])): ?>
                    <p class="badge bg-secondary"><?php echo htmlspecialchars($agent['rank']); ?></p>
                <?php endif; ?>
                <p class="mt-3">
                    <span class="badge <?php echo $agent['status'] == 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                        <?php echo ucfirst(htmlspecialchars($agent['status'])); ?>
                    </span>
                </p>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="row">
            <!-- Statistics Cards -->
            <div class="col-md-4 mb-3">
                <div class="card bg-primary text-white h-100">
                    <div class="card-body">
                        <h5 class="card-title">Total Assigned</h5>
                        <p class="card-text display-4"><?php echo $assigned_count; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card bg-warning text-dark h-100">
                    <div class="card-body">
                        <h5 class="card-title">Open Cases</h5>
                        <p class="card-text display-4"><?php echo $open_count; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card bg-success text-white h-100">
                    <div class="card-body">
                        <h5 class="card-title">Closed Cases</h5>
                        <p class="card-text display-4"><?php echo $closed_count; ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <a href="view_assigned.php" class="btn btn-primary btn-lg w-100">
                                    <i class="fas fa-list me-2"></i>View Assigned Cases
                                </a>
                            </div>
                            <div class="col-md-6 mb-3">
                                <a href="../user/dashboard.php" class="btn btn-secondary btn-lg w-100">
                                    <i class="fas fa-user me-2"></i>User Dashboard
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Assigned Cases -->
<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Recently Assigned Cases</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Location</th>
                        <th>Incident Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recent_cases && $recent_cases->num_rows > 0): ?>
                        <?php while ($case = $recent_cases->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $case['incident_id']; ?></td>
                                <td><?php echo htmlspecialchars($case['title']); ?></td>
                                <td><?php echo htmlspecialchars($case['crime_type']); ?></td>
                                <td><?php echo htmlspecialchars($case['location']); ?></td>
                                <td><?php echo format_date($case['incident_date']); ?></td>
                                <td>
                                    <span class="badge <?php 
                                        echo $case['status'] == 'Closed' ? 'bg-success' : 
                                            ($case['status'] == 'New' ? 'bg-info' : 
                                            ($case['status'] == 'Under Investigation' ? 'bg-warning' : 'bg-secondary')); 
                                    ?>">
                                        <?php echo htmlspecialchars($case['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="view_case.php?id=<?php echo $case['incident_id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">No cases assigned yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <a href="view_assigned.php" class="btn btn-primary">View All Assigned Cases</a>
    </div>
</div>

<?php
// Include footer
include '../includes/footer.php';
?>
