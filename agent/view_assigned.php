<?php
/**
 * View Assigned Cases
 * Agent page for viewing assigned cases
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
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("i", $_SESSION['user_id']);
if (!$stmt->execute()) {
    die("Execute failed: " . $stmt->error);
}
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    $_SESSION['error'] = "Agent profile not found.";
    header("Location: ../logout.php");
    exit;
}
$agent = $result->fetch_assoc();
$stmt->close();

// Get search parameters
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$type_filter = isset($_GET['type']) ? (int)$_GET['type'] : 0;
$date_from = isset($_GET['date_from']) ? sanitize_input($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize_input($_GET['date_to']) : '';

// Build query
$sql = "SELECT i.incident_id, i.title, i.incident_date, i.location, i.reported_date, 
               ct.name as crime_type, sc.name as status, u.full_name as reporter_name, i.is_anonymous
        FROM incident_agent ia
        JOIN incidents i ON ia.incident_id = i.incident_id
        JOIN crime_types ct ON i.crime_type_id = ct.type_id
        JOIN status_codes sc ON i.status_id = sc.status_id
        JOIN users u ON i.user_id = u.user_id
        WHERE ia.agent_id = ?";
$params = array($agent['agent_id']);
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

$sql .= " ORDER BY i.incident_id DESC";

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
if (!$result) {
    die("Get result failed: " . $conn->error);
}

// Fetch all cases into an array
$cases = [];
while ($row = $result->fetch_assoc()) {
    // Placeholder for repost count (since no repost mechanism is confirmed)
    $row['repost_count'] = 0;
    $cases[] = $row;
}
$stmt->close();

// Get crime types for filter
$crime_types = get_crime_types($conn);

// Get status codes for filter
$status_codes = get_status_codes($conn);

// Include header
include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Assigned Cases</h2>
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
                    <input type="text" class="form-control" name="search" placeholder="Search cases" value="<?php echo htmlspecialchars($search); ?>">
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
                <a href="view_assigned.php" class="btn btn-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Cases Table -->
<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Assigned Cases</h5>
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
                    <?php if (!empty($cases)): ?>
                        <?php foreach ($cases as $case): ?>
                            <tr>
                                <td><?php echo $case['incident_id']; ?></td>
                                <td><?php echo htmlspecialchars($case['title']); ?></td>
                                <td><?php echo htmlspecialchars($case['crime_type']); ?></td>
                                <td><?php echo htmlspecialchars($case['location']); ?></td>
                                <td><?php echo $case['is_anonymous'] ? 'Anonymous' : htmlspecialchars($case['reporter_name']); ?></td>
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
                                    <div class="btn-group">
                                        <a href="view_case.php?id=<?php echo $case['incident_id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <a href="update_status.php?id=<?php echo $case['incident_id']; ?>" class="btn btn-sm btn-warning">
                                            <i class="fas fa-edit"></i> Update
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center">No cases assigned yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
// Close connection
$conn->close();

// Include footer
include '../includes/footer.php';
?>