<?php
/**
 * View Reports
 * Admin page for viewing all crime reports
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

// Get search parameters
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$type_filter = isset($_GET['type']) ? (int)$_GET['type'] : 0;
$date_from = isset($_GET['date_from']) ? sanitize_input($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize_input($_GET['date_to']) : '';

// Build query
$sql = "SELECT i.*, ct.name as crime_type, sc.name as status, u.full_name as reporter_name
        FROM incidents i
        JOIN crime_types ct ON i.crime_type_id = ct.type_id
        JOIN status_codes sc ON i.status_id = sc.status_id
        JOIN users u ON i.user_id = u.user_id
        WHERE 1=1";
$params = array();
$types = "";

if (!empty($search)) {
    $sql .= " AND (i.title LIKE ? OR i.description LIKE ? OR i.location LIKE ? OR u.full_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ssss";
}

if (!empty($status_filter)) {
    $sql .= " AND sc.name = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($type_filter > 0) {
    $sql .= " AND i.crime_type_id = ?";
    $params[] = $type_filter;
    $types .= "i";
}

if (!empty($date_from)) {
    $sql .= " AND i.incident_date >= ?";
    $params[] = $date_from . " 00:00:00";
    $types .= "s";
}

if (!empty($date_to)) {
    $sql .= " AND i.incident_date <= ?";
    $params[] = $date_to . " 23:59:59";
    $types .= "s";
}

$sql .= " ORDER BY i.incident_id DESC";

// Prepare and execute query
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

if (!$stmt->execute()) {
    die("Execute failed: " . $conn->error);
}

$result = $stmt->get_result();
if ($result === false) {
    die("Get result failed: " . $conn->error);
}

// Fetch all reports into an array
$reports = [];
while ($row = $result->fetch_assoc()) {
    $reports[] = $row;
}

// Get crime types for filter
$crime_types = get_crime_types($conn);

// Get status codes for filter
$status_codes = get_status_codes($conn);

// Check if viewing a specific report
$viewing_report = false;
$report = null;
$assigned_agent = null;
$updates = array();

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $incident_id = (int)$_GET['id'];
    $report = get_incident_by_id($conn, $incident_id);
    
    if ($report) {
        $viewing_report = true;
        
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
        
        while ($row = $updates_result->fetch_assoc()) {
            $updates[] = $row;
        }
    }
}

// Include header
include '../includes/header.php';
?>

<?php if ($viewing_report && $report): ?>
    <!-- View Single Report -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>View Report #<?php echo $report['incident_id']; ?></h2>
        <div>
            <a href="assign_report.php?id=<?php echo $report['incident_id']; ?>" class="btn btn-primary me-2">
                <i class="fas fa-user-plus me-2"></i>Assign Agent
            </a>
            <a href="view_reports.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Reports
            </a>
        </div>
    </div>
    
    <div class="row">
        <!-- Report Details -->
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Report Details</h5>
                </div>
                <div class="card-body">
                    <h4><?php echo htmlspecialchars($report['title']); ?></h4>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p><strong>Crime Type:</strong> <?php echo htmlspecialchars($report['crime_type']); ?></p>
                            <p><strong>Location:</strong> <?php echo htmlspecialchars($report['location']); ?></p>
                            <p><strong>Incident Date:</strong> <?php echo format_date($report['incident_date']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Status:</strong> 
                                <span class="badge <?php 
                                    echo $report['status'] == 'Closed' ? 'bg-success' : 
                                        ($report['status'] == 'New' ? 'bg-info' : 
                                        ($report['status'] == 'Under Investigation' ? 'bg-warning' : 'bg-secondary')); 
                                ?>">
                                    <?php echo htmlspecialchars($report['status']); ?>
                                </span>
                            </p>
                            <p><strong>Reported By:</strong> <?php echo $report['is_anonymous'] ? 'Anonymous' : htmlspecialchars($report['reporter_name']); ?></p>
                            <p><strong>Reported Date:</strong> <?php echo format_date($report['reported_date']); ?></p>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <h5>Description</h5>
                        <p><?php echo nl2br(htmlspecialchars($report['description'])); ?></p>
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
            <!-- Assigned Agent -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Assigned Agent</h5>
                </div>
                <div class="card-body">
                    <?php if ($assigned_agent): ?>
                        <div class="text-center mb-3">
                            <img src="https://via.placeholder.com/150" class="rounded-circle" alt="Agent Photo">
                        </div>
                        <h5 class="text-center"><?php echo htmlspecialchars($assigned_agent['full_name']); ?></h5>
                        <p class="text-center text-muted"><?php echo htmlspecialchars($assigned_agent['badge_number']); ?> - <?php echo htmlspecialchars($assigned_agent['department']); ?></p>
                        
                        <hr>
                        
                        <div class="mb-2">
                            <i class="fas fa-envelope me-2"></i> <?php echo htmlspecialchars($assigned_agent['email']); ?>
                        </div>
                        <?php if (!empty($assigned_agent['phone'])): ?>
                            <div class="mb-2">
                                <i class="fas fa-phone me-2"></i> <?php echo htmlspecialchars($assigned_agent['phone']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="d-grid gap-2 mt-3">
                            <a href="assign_report.php?id=<?php echo $report['incident_id']; ?>" class="btn btn-outline-primary">
                                <i class="fas fa-exchange-alt me-2"></i>Reassign
                            </a>
                        </div>
                    <?php else: ?>
                        <p class="text-center">No agent assigned yet.</p>
                        <div class="d-grid gap-2">
                            <a href="assign_report.php?id=<?php echo $report['incident_id']; ?>" class="btn btn-primary">
                                <i class="fas fa-user-plus me-2"></i>Assign Agent
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="update_status.php?id=<?php echo $report['incident_id']; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-edit me-2"></i>Update Status
                        </a>
                        <a href="#" class="btn btn-outline-primary">
                            <i class="fas fa-print me-2"></i>Print Report
                        </a>
                        <a href="export_csv.php?id=<?php echo $report['incident_id']; ?>" class="btn btn-outline-primary">
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
<?php else: ?>
    <!-- View All Reports -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>View Reports</h2>
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
        </a>
    </div>
    
    <!-- Search and Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" action="" class="row g-3">
                <div class="col-md-4">
                    <div class="input-group">
                        <input type="text" class="form-control" name="search" placeholder="Search reports" value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <?php foreach ($status_codes as $status): ?>
                            <option value="<?php echo $status['name']; ?>" <?php echo $status_filter == $status['name'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($status['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="type" class="form-select">
                        <option value="0">All Types</option>
                        <?php foreach ($crime_types as $type): ?>
                            <option value="<?php echo $type['type_id']; ?>" <?php echo $type_filter == $type['type_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" class="form-control" name="date_from" placeholder="From Date" value="<?php echo $date_from; ?>">
                </div>
                <div class="col-md-2">
                    <input type="date" class="form-control" name="date_to" placeholder="To Date" value="<?php echo $date_to; ?>">
                </div>
                <div class="col-md-12 d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary me-2">Apply Filters</button>
                    <a href="view_reports.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Reports Table -->
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Reports</h5>
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
                            <th>Reporter</th>
                            <th>Incident Date</th>
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
                                    <td><?php echo htmlspecialchars($report['location']); ?></td>
                                    <td><?php echo $report['is_anonymous'] ? 'Anonymous' : htmlspecialchars($report['reporter_name']); ?></td>
                                    <td><?php echo format_date($report['incident_date']); ?></td>
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
                                        <div class="btn-group">
                                            <a href="view_reports.php?id=<?php echo $report['incident_id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="assign_report.php?id=<?php echo $report['incident_id']; ?>" class="btn btn-sm btn-success">
                                                <i class="fas fa-user-plus"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center">No reports found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
// Close statement and connection
$stmt->close();
$conn->close();

// Include footer
include '../includes/footer.php';
?>