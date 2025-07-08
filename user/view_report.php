<?php
/**
 * View Report
 * User page for viewing a specific report
 * Author: Crime Reporting System
 * Date: 2023-05-07
 */

// Include auth check
require_once '../includes/auth_check.php';

// Include database connection
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Ensure user is logged in
if (!is_logged_in()) {
    $_SESSION['error'] = "You must be logged in to view reports.";
    header("Location: ../login.php");
    exit;
}

// Check if ID parameter exists
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Invalid report ID.";
    header("Location: view_my_reports.php");
    exit;
}

$incident_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

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
    $_SESSION['error'] = "Report not found.";
    header("Location: view_my_reports.php");
    exit;
}

$incident = $result->fetch_assoc();

// Check if user owns this report or is admin/agent
if ($incident['user_id'] != $user_id && !has_role('admin') && !has_role('agent')) {
    $_SESSION['error'] = "You don't have permission to view this report.";
    header("Location: view_my_reports.php");
    exit;
}

// Get assigned agent
$assigned_agent = get_assigned_agent($conn, $incident_id);

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

// Check if this is a new report submission
$is_new = isset($_GET['new']) && $_GET['new'] == 1;

// Include header
include '../includes/header.php';
?>

<?php if ($is_new): ?>
    <div class="alert alert-success mb-4">
        <h4 class="alert-heading">Report Submitted Successfully!</h4>
        <p>Your report has been submitted and will be reviewed by our team. You can track the status of your report on this page.</p>
    </div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Report #<?php echo $incident_id; ?></h2>
    <a href="view_my_reports.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i>Back to My Reports
    </a>
</div>

<div class="row">
    <!-- Report Details -->
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Report Details</h5>
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
                        <p><strong>Reported Date:</strong> <?php echo format_date($incident['reported_date']); ?></p>
                        <p><strong>Anonymous:</strong> <?php echo $incident['is_anonymous'] ? 'Yes' : 'No'; ?></p>
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
                    <p class="text-center">No updates yet. Your report is being processed.</p>
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
        <!-- Case Status -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Case Status</h5>
            </div>
            <div class="card-body text-center">
                <div class="mb-3">
                    <span class="badge <?php 
                        echo $incident['status'] == 'Closed' ? 'bg-success' : 
                            ($incident['status'] == 'New' ? 'bg-info' : 
                            ($incident['status'] == 'Under Investigation' ? 'bg-warning' : 'bg-secondary')); 
                    ?> p-2 fs-6">
                        <?php echo htmlspecialchars($incident['status']); ?>
                    </span>
                </div>
                
                <p class="mb-0">
                    <?php
                    switch ($incident['status']) {
                        case 'New':
                            echo 'Your report has been received and is awaiting review.';
                            break;
                        case 'Assigned':
                            echo 'Your report has been assigned to an agent for investigation.';
                            break;
                        case 'Under Investigation':
                            echo 'Your report is currently being investigated.';
                            break;
                        case 'Pending Evidence':
                            echo 'Additional evidence is needed for your report.';
                            break;
                        case 'Closed':
                            echo 'Your report has been resolved and closed.';
                            break;
                        default:
                            echo 'Your report is being processed.';
                    }
                    ?>
                </p>
            </div>
        </div>
        
        <!-- Assigned Agent -->
        <?php if ($assigned_agent): ?>
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Assigned Agent</h5>
                </div>
                <div class="card-body text-center">
                    <img src="https://via.placeholder.com/100" class="rounded-circle mb-3" alt="Agent Photo">
                    <h5><?php echo htmlspecialchars($assigned_agent['full_name']); ?></h5>
                    <p class="text-muted"><?php echo htmlspecialchars($assigned_agent['department']); ?></p>
                    
                    <?php if (!$incident['is_anonymous']): ?>
                        <hr>
                        <div class="text-start">
                            <p><i class="fas fa-envelope me-2"></i> <?php echo htmlspecialchars($assigned_agent['email']); ?></p>
                            <?php if (!empty($assigned_agent['phone'])): ?>
                                <p><i class="fas fa-phone me-2"></i> <?php echo htmlspecialchars($assigned_agent['phone']); ?></p>
                            <?php endif; ?>
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
                    <a href="#" class="btn btn-outline-primary">
                        <i class="fas fa-print me-2"></i>Print Report
                    </a>
                    <a href="submit_report.php" class="btn btn-success">
                        <i class="fas fa-plus-circle me-2"></i>Submit New Report
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
