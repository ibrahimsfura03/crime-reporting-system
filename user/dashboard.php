<?php
require_once '../includes/auth_check.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

if (!is_logged_in()) {
    $_SESSION['error'] = "You must be logged in to access this page.";
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user = get_user_by_id($conn, $user_id);

if (!$user) {
    $_SESSION['error'] = "User not found.";
    header("Location: ../index.php");
    exit;
}

// Get user's reports count
$sql = "SELECT COUNT(*) as count FROM incidents WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$reports_count = $row['count'];

// Get user's open reports count
$sql = "SELECT COUNT(*) as count 
        FROM incidents i 
        JOIN status_codes sc ON i.status_id = sc.status_id 
        WHERE i.user_id = ? AND sc.name != 'Closed'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$open_reports_count = $row['count'];

// Get user's recent reports
$sql = "SELECT i.incident_id, i.title, i.incident_date, i.reported_date, ct.name as crime_type, sc.name as status
        FROM incidents i
        JOIN crime_types ct ON i.crime_type_id = ct.type_id
        JOIN status_codes sc ON i.status_id = sc.status_id
        WHERE i.user_id = ?
        ORDER BY i.reported_date DESC
        LIMIT 5";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_reports = $stmt->get_result();

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2>User Dashboard</h2>
    </div>
</div>


<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-6 mb-3">
        <div class="card bg-custom-primary text-white h-100">
            <div class="card-body">
                <h5 class="card-title">Total Reports</h5>
                <p class="card-text display-4"><?php echo $reports_count; ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-3">
        <div class="card bg-custom-warning text-dark h-100">
            <div class="card-body">
                <h5 class="card-title">Open Reports</h5>
                <p class="card-text display-4"><?php echo $open_reports_count; ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-custom-primary text-white">
                <h5 class="mb-0">Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <a href="submit_report.php" class="btn btn-custom-success btn-lg w-100">
                            <i class="fas fa-plus-circle me-2"></i>Submit New Report
                        </a>
                    </div>
                    <div class="col-md-6 mb-3">
                        <a href="view_my_reports.php" class="btn btn-custom-primary btn-lg w-100">
                            <i class="fas fa-list me-2"></i>View My Reports
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Reports -->
<div class="card">
    <div class="card-header bg-custom-primary text-white">
        <h5 class="mb-0">Recent Reports</h5>
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
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recent_reports && $recent_reports->num_rows > 0): ?>
                        <?php while ($report = $recent_reports->fetch_assoc()): ?>
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
                                    <a href="view_report.php?id=<?php echo $report['incident_id']; ?>" class="btn btn-sm btn-custom-primary">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">No reports submitted yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <a href="view_my_reports.php" class="btn btn-custom-primary">View All Reports</a>
    </div>
</div>

<?php
include '../includes/footer.php';
?>
