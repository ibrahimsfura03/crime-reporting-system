<?php
require_once '../includes/auth_check.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

if (!has_role('admin')) {
    $_SESSION['error'] = "You don't have permission to access this page.";
    header("Location: ../login.php");
    exit;
}

// Get statistics
$total_reports = count_incidents($conn);
$open_reports = count_incidents($conn, 'Under Investigation');
$closed_reports = count_incidents($conn, 'Closed');
$new_reports = count_incidents($conn, 'New');
$pending_reports = count_incidents($conn, 'Pending Evidence');

// Get user counts
$sql = "SELECT COUNT(*) as count FROM users WHERE role = 'user'";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$user_count = $row['count'];

$sql = "SELECT COUNT(*) as count FROM agents";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$agent_count = $row['count'];

// Get recent reports
$sql = "SELECT i.incident_id, i.title, i.incident_date, i.reported_date, 
               ct.name as crime_type, sc.name as status, u.full_name as reporter
        FROM incidents i
        JOIN crime_types ct ON i.crime_type_id = ct.type_id
        JOIN status_codes sc ON i.status_id = sc.status_id
        JOIN users u ON i.user_id = u.user_id
        ORDER BY i.reported_date DESC
        LIMIT 5";
$recent_reports = $conn->query($sql);

// Get reports per agent
$sql = "SELECT a.agent_id, u.full_name, COUNT(ia.incident_id) as report_count
        FROM agents a
        JOIN users u ON a.user_id = u.user_id
        LEFT JOIN incident_agent ia ON a.agent_id = ia.agent_id
        GROUP BY a.agent_id
        ORDER BY report_count DESC
        LIMIT 5";
$agent_reports = $conn->query($sql);

// Get user data for profile
$user_id = $_SESSION['user_id'];
$user = get_user_by_id($conn, $user_id);

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2>Admin Dashboard</h2>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-4 mb-3">
        <div class="card bg-custom-primary text-white h-100">
            <div class="card-body">
                <h5 class="card-title">Total Reports</h5>
                <p class="card-text display-4"><?php echo $total_reports; ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card bg-custom-warning text-dark h-100">
            <div class="card-body">
                <h5 class="card-title">Open Reports</h5>
                <p class="card-text display-4"><?php echo $open_reports; ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card bg-custom-success text-white h-100">
            <div class="card-body">
                <h5 class="card-title">Closed Reports</h5>
                <p class="card-text display-4"><?php echo $closed_reports; ?></p>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4 mb-3">
        <div class="card bg-info text-white h-100">
            <div class="card-body">
                <h5 class="card-title">New Reports</h5>
                <p class="card-text display-4"><?php echo $new_reports; ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card bg-secondary text-white h-100">
            <div class="card-body">
                <h5 class="card-title">Registered Users</h5>
                <p class="card-text display-4"><?php echo $user_count; ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card bg-danger text-white h-100">
            <div class="card-body">
                <h5 class="card-title">Active Agents</h5>
                <p class="card-text display-4"><?php echo $agent_count; ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Chart -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-custom-primary text-white">
                <h5 class="mb-0">Reports Overview</h5>
            </div>
            <div class="card-body">
                <canvas id="reportsChart" width="400" height="200"></canvas>
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
                    <div class="col-md-3 mb-3">
                        <a href="manage_users.php" class="btn btn-lg btn-outline-primary w-100">
                            <i class="fas fa-users me-2"></i>Manage Users
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="manage_agents.php" class="btn btn-lg btn-outline-primary w-100">
                            <i class="fas fa-user-shield me-2"></i>Manage Agents
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="assign_report.php" class="btn btn-lg btn-outline-primary w-100">
                            <i class="fas fa-tasks me-2"></i>Assign Reports
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="export_csv.php" class="btn btn-lg btn-outline-primary w-100">
                            <i class="fas fa-file-csv me-2"></i>Export Reports
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Reports -->
<div class="row mb-4">
    <div class="col-md-12">
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
                                <th>Reporter</th>
                                <th>Incident Date</th>
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
                                        <td><?php echo htmlspecialchars($report['reporter']); ?></td>
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
                                                <a href="edit_report.php?id=<?php echo $report['incident_id']; ?>" class="btn btn-sm btn-warning">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="assign_report.php?id=<?php echo $report['incident_id']; ?>" class="btn btn-sm btn-success">
                                                    <i class="fas fa-user-plus"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">No reports found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <a href="view_reports.php" class="btn btn-custom-primary">View All Reports</a>
            </div>
        </div>
    </div>
</div>

<script>
// Chart for reports overview
document.addEventListener('DOMContentLoaded', function() {
    var ctx = document.getElementById('reportsChart').getContext('2d');
    var myChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Total', 'New', 'Under Investigation', 'Pending Evidence', 'Closed'],
            datasets: [{
                label: 'Reports',
                data: [<?php echo $total_reports; ?>, <?php echo $new_reports; ?>, <?php echo $open_reports; ?>, <?php echo $pending_reports; ?>, <?php echo $closed_reports; ?>],
                backgroundColor: [
                    '#2c3e50',
                    '#3498db',
                    '#f39c12',
                    '#95a5a6',
                    '#27ae60'
                ],
                borderColor: [
                    '#2c3e50',
                    '#3498db',
                    '#f39c12',
                    '#95a5a6',
                    '#27ae60'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
});
</script>

<?php
include '../includes/footer.php';
?>
