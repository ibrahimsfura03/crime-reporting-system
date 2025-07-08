<?php
/**
 * Assign Report
 * Admin page for assigning reports to agents
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

// Get all reports for selection if no ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    // Get all reports
    $sql = "SELECT i.incident_id, i.title, ct.name as crime_type, sc.name as status
            FROM incidents i
            JOIN crime_types ct ON i.crime_type_id = ct.type_id
            JOIN status_codes sc ON i.status_id = sc.status_id
            ORDER BY i.incident_id DESC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    if (!$stmt->execute()) {
        die("Execute failed: " . $stmt->error);
    }
    $result = $stmt->get_result();
    if ($result === false) {
        die("Get result failed: " . $conn->error);
    }
    
    // Fetch reports into array
    $reports = [];
    while ($row = $result->fetch_assoc()) {
        $reports[] = $row;
    }
    $stmt->close();
    
    // Include header
    include '../includes/header.php';
    ?>
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Select Report to Assign</h2>
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
        </a>
    </div>
    
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Available Reports</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($reports)): ?>
                            <?php foreach ($reports as $report): ?>
                                <tr>
                                    <td><?php echo $report['incident_id']; ?></td>
                                    <td><?php echo htmlspecialchars($report['title']); ?></td>
                                    <td><?php echo htmlspecialchars($report['crime_type']); ?></td>
                                    <td>
                                        <span class="badge <?php 
                                            echo $report['status'] == 'Closed' ? 'bg-success' : 
                                                ($report['status'] == 'New' ? 'bg-info' : 
                                                ($report['status'] == 'Under Investigation' ? 'bg-warning' : 'bg-secondary')); 
                                        ?>">
                                            <?php echo htmlspecialchars($report['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="assign_report.php?id=<?php echo $report['incident_id']; ?>" 
                                           class="btn btn-sm btn-primary <?php echo $report['status'] == 'Closed' ? 'disabled' : ''; ?>" 
                                           <?php echo $report['status'] == 'Closed' ? 'aria-disabled="true"' : ''; ?>>
                                            <i class="fas fa-user-plus me-1"></i> Assign
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center">No reports found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <?php
    include '../includes/footer.php';
    exit;
}

$incident_id = (int)$_GET['id'];

// Get incident details
$incident = get_incident_by_id($conn, $incident_id);

if (!$incident) {
    $_SESSION['error'] = "Report not found.";
    header("Location: view_reports.php");
    exit;
}

// Get current assigned agent
$assigned_agent = get_assigned_agent($conn, $incident_id);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $agent_id = (int)$_POST['agent_id'];
    $notes = sanitize_input($_POST['notes']);
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Check if already assigned
        if ($assigned_agent) {
            // Update assignment
            $sql = "UPDATE incident_agent SET agent_id = ?, notes = ? WHERE incident_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isi", $agent_id, $notes, $incident_id);
            $stmt->execute();
            $stmt->close();
        } else {
            // Create new assignment
            $sql = "INSERT INTO incident_agent (incident_id, agent_id, notes) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iis", $incident_id, $agent_id, $notes);
            $stmt->execute();
            $stmt->close();
        }
        
        // Update incident status to "Assigned" if it's "New"
        if ($incident['status'] == 'New') {
            // Get status ID for "Assigned"
            $sql = "SELECT status_id FROM status_codes WHERE name = 'Assigned'";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $status_id = $row['status_id'];
            $stmt->close();
            
            // Update incident status
            $sql = "UPDATE incidents SET status_id = ? WHERE incident_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $status_id, $incident_id);
            $stmt->execute();
            $stmt->close();
            
            // Add status update record
            $sql = "INSERT INTO incident_updates (incident_id, user_id, status_id, update_text) VALUES (?, ?, ?, ?)";
            $update_text = "Case assigned to agent.";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiis", $incident_id, $_SESSION['user_id'], $status_id, $update_text);
            $stmt->execute();
            $stmt->close();
        }
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['success'] = "Report assigned successfully.";
        header("Location: view_reports.php?id=$incident_id");
        exit;
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $_SESSION['error'] = "Error assigning report: " . $e->getMessage();
    }
}

// Get all active agents
$sql = "SELECT a.*, u.full_name, u.email, u.phone
        FROM agents a
        JOIN users u ON a.user_id = u.user_id
        WHERE a.status = 'active'
        ORDER BY u.full_name";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
if (!$stmt->execute()) {
    die("Execute failed: " . $stmt->error);
}
$result = $stmt->get_result();
if ($result === false) {
    die("Get result failed: " . $conn->error);
}
$agents_array = [];
while ($row = $result->fetch_assoc()) {
    $agents_array[] = $row;
}
$stmt->close();

// Include header
include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Assign Report #<?php echo $incident_id; ?></h2>
    <div>
        <a href="view_reports.php?id=<?php echo $incident_id; ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Report
        </a>
    </div>
</div>

<div class="row">
    <!-- Report Details -->
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Report Details</h5>
            </div>
            <div class="card-body">
                <h5><?php echo htmlspecialchars($incident['title']); ?></h5>
                
                <div class="mb-3">
                    <p><strong>Crime Type:</strong> <?php echo htmlspecialchars($incident['crime_type']); ?></p>
                    <p><strong>Location:</strong> <?php echo htmlspecialchars($incident['location']); ?></p>
                    <p><strong>Incident Date:</strong> <?php echo format_date($incident['incident_date']); ?></p>
                    <p><strong>Status:</strong> 
                        <span class="badge <?php 
                            echo $incident['status'] == 'Closed' ? 'bg-success' : 
                                ($incident['status'] == 'New' ? 'bg-info' : 
                                ($incident['status'] == 'Under Investigation' ? 'bg-warning' : 'bg-secondary')); 
                        ?>">
                            <?php echo htmlspecialchars($incident['status']); ?>
                        </span>
                    </p>
                </div>
                
                <div class="mb-3">
                    <h6>Description</h6>
                    <p class="text-muted"><?php echo nl2br(htmlspecialchars(substr($incident['description'], 0, 200))); ?>...</p>
                </div>
                
                <?php if ($assigned_agent): ?>
                    <div class="alert alert-info">
                        <p class="mb-0"><strong>Currently assigned to:</strong> <?php echo htmlspecialchars($assigned_agent['full_name']); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Assign Form -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Assign to Agent</h5>
            </div>
            <div class="card-body">
                <?php if ($incident): ?>
                    <form method="post" action="">
                        <div class="mb-3">
                            <label for="agent_id" class="form-label">Select Agent</label>
                            <select class="form-select" id="agent_id" name="agent_id" required>
                                <option value="">Select Agent</option>
                                <?php if (!empty($agents_array)): ?>
                                    <?php foreach ($agents_array as $agent): ?>
                                        <option value="<?php echo $agent['agent_id']; ?>" <?php echo ($assigned_agent && $assigned_agent['agent_id'] == $agent['agent_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($agent['full_name']); ?> (<?php echo htmlspecialchars($agent['badge_number']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="" disabled>No agents available</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Assignment Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo $assigned_agent && isset($assigned_agent['notes']) ? htmlspecialchars($assigned_agent['notes']) : ''; ?></textarea>
                            <div class="form-text">Add any specific instructions or notes for the agent.</div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary" <?php echo empty($agents_array) || $incident['status'] == 'Closed' ? 'disabled' : ''; ?>>
                                <?php echo $assigned_agent ? 'Update Assignment' : 'Assign Report'; ?>
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <p class="text-danger">Invalid report ID.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Agent List -->
        <div class="card mt-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Available Agents</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Badge</th>
                                <th>Department</th>
                                <th>Contact</th>
                                <th>Workload</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($agents_array)): ?>
                                <?php foreach ($agents_array as $agent): ?>
                                    <?php
                                    // Get agent workload
                                    $sql = "SELECT COUNT(*) as count FROM incident_agent WHERE agent_id = ?";
                                    $stmt = $conn->prepare($sql);
                                    $stmt->bind_param("i", $agent['agent_id']);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    $workload = $result->fetch_assoc()['count'];
                                    $stmt->close();
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($agent['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($agent['badge_number']); ?></td>
                                        <td><?php echo htmlspecialchars($agent['department']); ?></td>
                                        <td>
                                            <small>
                                                <div><i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($agent['email']); ?></div>
                                                <?php if (!empty($agent['phone'])): ?>
                                                    <div><i class="fas fa-phone me-1"></i> <?php echo htmlspecialchars($agent['phone']); ?></div>
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $workload > 5 ? 'bg-danger' : ($workload > 2 ? 'bg-warning' : 'bg-success'); ?>">
                                                <?php echo $workload; ?> cases
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center">No agents available. Please add agents in the system.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Close connection
$conn->close();

// Include footer
include '../includes/footer.php';
?>