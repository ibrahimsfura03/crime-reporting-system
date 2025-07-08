<?php
/**
 * Update Status
 * Agent page for updating case status
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

// Check if ID parameter exists
$incident_id = 0;
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $incident_id = (int)$_GET['id'];
    
    // Check if agent is assigned to this case
    $sql = "SELECT * FROM incident_agent WHERE incident_id = ? AND agent_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $incident_id, $agent['agent_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        $_SESSION['error'] = "You are not assigned to this case.";
        header("Location: view_assigned.php");
        exit;
    }
    
    // Get incident details
    $sql = "SELECT i.*, ct.name as crime_type, sc.name as status
            FROM incidents i
            JOIN crime_types ct ON i.crime_type_id = ct.type_id
            JOIN status_codes sc ON i.status_id = sc.status_id
            WHERE i.incident_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $incident_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        $_SESSION['error'] = "Case not found.";
        header("Location: view_assigned.php");
        exit;
    }
    
    $incident = $result->fetch_assoc();
}

// Get status codes for dropdown
$status_codes = get_status_codes($conn);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $update_incident_id = (int)$_POST['incident_id'];
    $status_id = (int)$_POST['status_id'];
    $update_text = sanitize_input($_POST['update_text']);
    
    // Check if agent is assigned to this case
    $sql = "SELECT * FROM incident_agent WHERE incident_id = ? AND agent_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $update_incident_id, $agent['agent_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        $_SESSION['error'] = "You are not assigned to this case.";
    } else {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Update incident status
            $sql = "UPDATE incidents SET status_id = ? WHERE incident_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $status_id, $update_incident_id);
            $stmt->execute();
            
            // Add status update record
            $sql = "INSERT INTO incident_updates (incident_id, user_id, status_id, update_text) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiis", $update_incident_id, $_SESSION['user_id'], $status_id, $update_text);
            $stmt->execute();
            
            // Commit transaction
            $conn->commit();
            
            $_SESSION['success'] = "Case status updated successfully.";
            header("Location: view_case.php?id=$update_incident_id");
            exit;
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $_SESSION['error'] = "Error updating case status: " . $e->getMessage();
        }
    }
}

// Get assigned cases for dropdown if no specific case is selected
if ($incident_id == 0) {
    $sql = "SELECT i.incident_id, i.title, sc.name as status
            FROM incident_agent ia
            JOIN incidents i ON ia.incident_id = i.incident_id
            JOIN status_codes sc ON i.status_id = sc.status_id
            WHERE ia.agent_id = ?
            ORDER BY i.incident_id DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $agent['agent_id']);
    $stmt->execute();
    $assigned_cases = $stmt->get_result();
}

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><?php echo $incident_id > 0 ? "Update Case #$incident_id Status" : "Update Case Status"; ?></h2>
    <a href="<?php echo $incident_id > 0 ? "view_case.php?id=$incident_id" : "view_assigned.php"; ?>" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i>Back to <?php echo $incident_id > 0 ? "Case" : "Assigned Cases"; ?>
    </a>
</div>

<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Update Status</h5>
    </div>
    <div class="card-body">
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <form method="post" action="">
            <?php if ($incident_id == 0): ?>
                <div class="mb-3">
                    <label for="incident_id" class="form-label">Select Case</label>
                    <select class="form-select" id="incident_id" name="incident_id" required>
                        <option value="">Select Case</option>
                        <?php if ($assigned_cases && $assigned_cases->num_rows > 0): ?>
                            <?php while ($case = $assigned_cases->fetch_assoc()): ?>
                                <option value="<?php echo $case['incident_id']; ?>">
                                    #<?php echo $case['incident_id']; ?> - <?php echo htmlspecialchars($case['title']); ?> (<?php echo htmlspecialchars($case['status']); ?>)
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
            <?php else: ?>
                <input type="hidden" name="incident_id" value="<?php echo $incident_id; ?>">
                
                <div class="mb-3">
                    <h5>Case Details</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Title:</strong> <?php echo htmlspecialchars($incident['title']); ?></p>
                            <p><strong>Type:</strong> <?php echo htmlspecialchars($incident['crime_type']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Current Status:</strong> 
                                <span class="badge <?php 
                                    echo $incident['status'] == 'Closed' ? 'bg-success' : 
                                        ($incident['status'] == 'New' ? 'bg-info' : 
                                        ($incident['status'] == 'Under Investigation' ? 'bg-warning' : 'bg-secondary')); 
                                ?>">
                                    <?php echo htmlspecialchars($incident['status']); ?>
                                </span>
                            </p>
                            <p><strong>Incident Date:</strong> <?php echo format_date($incident['incident_date']); ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="mb-3">
                <label for="status_id" class="form-label">New Status</label>
                <select class="form-select" id="status_id" name="status_id" required>
                    <option value="">Select Status</option>
                    <?php foreach ($status_codes as $status): ?>
                        <option value="<?php echo $status['status_id']; ?>" <?php echo isset($incident) && $incident['status'] == $status['name'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($status['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="mb-3">
                <label for="update_text" class="form-label">Update Notes</label>
                <textarea class="form-control" id="update_text" name="update_text" rows="5" required></textarea>
                <div class="form-text">Provide details about this status update.</div>
            </div>
            
            <button type="submit" class="btn btn-primary">Update Status</button>
        </form>
    </div>
</div>

<?php
include '../includes/footer.php';
?>
