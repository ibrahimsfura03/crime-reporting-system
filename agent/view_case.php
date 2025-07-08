<?php
/**
 * View Case
 * Agent page for viewing a specific case
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
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Invalid case ID.";
    header("Location: view_assigned.php");
    exit;
}

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
$sql = "SELECT i.*, ct.name as crime_type, sc.name as status, u.full_name as reporter_name, u.email as reporter_email, u.phone as reporter_phone
        FROM incidents i
        JOIN crime_types ct ON i.crime_type_id = ct.type_id
        JOIN status_codes sc ON i.status_id = sc.status_id
        JOIN users u ON i.user_id = u.user_id
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

// Get updates for this incident
$sql = "SELECT iu.*, sc.name as status_name, u.full_name
        FROM incident_updates iu
        JOIN status_codes sc ON iu.status_id = sc.status_id
        JOIN users u ON iu.user_id = u.user_id
        WHERE iu.incident_id = ?
        ORDER BY iu.update_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $incident_id);
$stmt->execute();
$updates_result = $stmt->get_result();
$updates = [];

while ($row = $updates_result->fetch_assoc()) {
    $updates[] = $row;
}

// Include header
include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Case #<?php echo $incident_id; ?></h2>
    <div>
        <a href="update_status.php?id=<?php echo $incident_id; ?>" class="btn btn-warning me-2">
            <i class="fas fa-edit me-2"></i>Update Status
        </a>
        <a href="view_assigned.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Cases
        </a>
    </div>
</div>

<div class="row">
    <!-- Case Details -->
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Case Details</h5>
            </div>
            <div class="card-body">
                <h4><?php echo htmlspecialchars($incident['title']); ?></h4>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>Crime Type:</strong> <?php echo htmlspecialchars($incident['crime_type']); ?></p>
                        <p><strong>Location:</strong> <?php echo htmlspecialchars($incident['location']); ?></p>
                        <p><strong>Incident Date:</strong> <?php echo format_date($incident['incident_date']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Status:</strong> 
                            <span class="badge <?php 
                                echo $incident['status'] == 'Closed' ? 'bg-success' : 
                                    ($incident['status'] == 'New' ? 'bg-info' : 
                                    ($incident['status'] == 'Under Investigation' ? 'bg-warning' : 'bg-secondary')); 
                            ?>">
                                <?php echo htmlspecialchars($incident['status']); ?>
                            </span>
                        </p>
                        <p><strong>Reported By:</strong> <?php echo $incident['is_anonymous'] ? 'Anonymous' : htmlspecialchars($incident['reporter_name']); ?></p>
                        <p><strong>Reported Date:</strong> <?php echo format_date($incident['reported_date']); ?></p>
                    </div>
                </div>
                
                <div class="mb-3">
                    <h5>Description</h5>
                    <p><?php echo nl2br(htmlspecialchars($incident['description'])); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Status Updates -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Status Updates</h5>
            </div>
            <div class="card-body">
                <?php if (empty($updates)): ?>
                    <p class="text-center">No updates yet.</p>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($updates as $update): ?>
                            <div class="timeline-item">
                                <div class="timeline-item-marker">
                                    <div class="timeline-item-marker-text"><?php echo date('M d', strtotime($update['update_date'])); ?></div>
                                    <div class="timeline-item-marker-indicator bg-primary"></div>
                                </div>
                                <div class="timeline-item-content">
                                    <p class="mb-0 fw-bold">Status changed to <span class="badge bg-primary"><?php echo htmlspecialchars($update['status_name']); ?></span></p>
                                    <p class="mb-0 small text-muted">By <?php echo htmlspecialchars($update['full_name']); ?> on <?php echo format_date($update['update_date']); ?></p>
                                    <?php if (!empty($update['update_text'])): ?>
                                        <p class="mt-2 mb-0"><?php echo nl2br(htmlspecialchars($update['update_text'])); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Sidebar -->
    <div class="col-md-4">
        <!-- Reporter Information -->
        <?php if (!$incident['is_anonymous']): ?>
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Reporter Information</h5>
                </div>
                <div class="card-body">
                    <h5><?php echo htmlspecialchars($incident['reporter_name']); ?></h5>
                    
                    <hr>
                    
                    <div class="mb-2">
                        <i class="fas fa-envelope me-2"></i> <?php echo htmlspecialchars($incident['reporter_email']); ?>
                    </div>
                    <?php if (!empty($incident['reporter_phone'])): ?>
                        <div class="mb-2">
                            <i class="fas fa-phone me-2"></i> <?php echo htmlspecialchars($incident['reporter_phone']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Actions -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="update_status.php?id=<?php echo $incident_id; ?>" class="btn btn-warning">
                        <i class="fas fa-edit me-2"></i>Update Status
                    </a>
                    <a href="#" class="btn btn-outline-primary">
                        <i class="fas fa-print me-2"></i>Print Case
                    </a>
                    <a href="../admin/export_csv.php?id=<?php echo $incident_id; ?>" class="btn btn-outline-primary">
                        <i class="fas fa-file-csv me-2"></i>Export as CSV
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .timeline {
        position: relative;
        padding-left: 1rem;
        margin: 1rem 0;
        border-left: 1px solid #dee2e6;
    }
    .timeline-item {
        position: relative;
        margin-bottom: 1.5rem;
    }
    .timeline-item-marker {
        position: absolute;
        left: -1rem;
        width: 1rem;
    }
    .timeline-item-marker-text {
        position: absolute;
        width: 6rem;
        left: -8rem;
        text-align: right;
        font-size: 0.8rem;
        font-weight: bold;
    }
    .timeline-item-marker-indicator {
        display: block;
        width: 10px;
        height: 10px;
        border-radius: 100%;
        margin-top: 0.25rem;
        margin-left: -0.25rem;
    }
    .timeline-item-content {
        padding-left: 0.5rem;
        padding-bottom: 1.5rem;
    }
</style>

<?php
// Include footer
include '../includes/footer.php';
?>
