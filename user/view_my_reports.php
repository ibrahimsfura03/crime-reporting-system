<?php
/**
 * View My Reports
 * User page for viewing their submitted reports
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
    $_SESSION['error'] = "You must be logged in to view your reports.";
    header("Location: ../login.php");
    exit;
}

// Get user ID from session
$user_id = $_SESSION['user_id'];

// Get search parameters
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$type_filter = isset($_GET['type']) ? (int)$_GET['type'] : 0;
$date_from = isset($_GET['date_from']) ? sanitize_input($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize_input($_GET['date_to']) : '';

// Build query
$sql = "SELECT i.incident_id, i.title, i.incident_date, i.reported_date, i.is_anonymous,
               ct.name as crime_type, sc.name as status
        FROM incidents i
        JOIN crime_types ct ON i.crime_type_id = ct.type_id
        JOIN status_codes sc ON i.status_id = sc.status_id
        WHERE i.user_id = ?";
$params = array($user_id);
$types = "i";

if (!empty($search)) {
    $sql .= " AND (i.title LIKE ? OR i.description LIKE ? OR i.location LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
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

$sql .= " ORDER BY i.reported_date DESC";

// Prepare and execute query
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param($types, ...$params);
if (!$stmt->execute()) {
    die("Execute failed: " . $stmt->error);
}
$result = $stmt->get_result();
if ($result === false) {
    die("Get result failed: " . $conn->error);
}

// Fetch all reports into an array
$reports = [];
while ($row = $result->fetch_assoc()) {
    // Placeholder for repost count (since no repost mechanism is confirmed)
    $row['repost_count'] = 0;
    $reports[] = $row;
}

// Get crime types for filter
$crime_types = get_crime_types($conn);

// Get status codes for filter
$status_codes = get_status_codes($conn);

// Include header
include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>My Reports</h2>
    <div>
        <a href="submit_report.php" class="btn btn-success me-2">
            <i class="fas fa-plus-circle me-2"></i>Submit New Report
        </a>
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
        </a>
    </div>
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
                <a href="view_my_reports.php" class="btn btn-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Reports Table -->
<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">My Reports</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Incident Date</th>
                        <th>Reported Date</th>
                        <th>Status</th>
                        <th>Anonymous</th>
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
                                <td><?php echo format_date($report['incident_date']); ?></td>
                                <td><?php echo format_date($report['reported_date']); ?></td>
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
                                    <?php echo $report['is_anonymous'] ? 'Yes' : 'No'; ?>
                                </td>
                                <td>
                                    <a href="view_report.php?id=<?php echo $report['incident_id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center">No reports found.</td>
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