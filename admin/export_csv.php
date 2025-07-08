<?php
/**
 * Export CSV
 * Admin page for exporting reports to CSV
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

// Check if exporting a specific report
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $incident_id = (int)$_GET['id'];
    
    // Get incident details
    $sql = "SELECT i.*, ct.name as crime_type, sc.name as status, u.full_name as reporter_name, u.email as reporter_email
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
        $_SESSION['error'] = "Report not found.";
        header("Location: view_reports.php");
        exit;
    }
    
    $incident = $result->fetch_assoc();
    
    // Get assigned agent
    $assigned_agent = get_assigned_agent($conn, $incident_id);
    
    // Get updates
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
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="report_' . $incident_id . '.csv"');
    
    // Create a file pointer connected to the output stream
    $output = fopen('php://output', 'w');
    
    // Output report details
    fputcsv($output, array('Report ID', $incident_id));
    fputcsv($output, array('Title', $incident['title']));
    fputcsv($output, array('Crime Type', $incident['crime_type']));
    fputcsv($output, array('Status', $incident['status']));
    fputcsv($output, array('Location', $incident['location']));
    fputcsv($output, array('Incident Date', format_date($incident['incident_date'])));
    fputcsv($output, array('Reported Date', format_date($incident['reported_date'])));
    fputcsv($output, array('Reporter', $incident['is_anonymous'] ? 'Anonymous' : $incident['reporter_name']));
    if (!$incident['is_anonymous']) {
        fputcsv($output, array('Reporter Email', $incident['reporter_email']));
    }
    fputcsv($output, array('Description', $incident['description']));
    
    // Output assigned agent
    fputcsv($output, array(''));
    fputcsv($output, array('Assigned Agent'));
    
    if ($assigned_agent) {
        fputcsv($output, array('Name', $assigned_agent['full_name']));
        fputcsv($output, array('Badge Number', $assigned_agent['badge_number']));
        fputcsv($output, array('Department', $assigned_agent['department']));
        fputcsv($output, array('Email', $assigned_agent['email']));
        fputcsv($output, array('Phone', $assigned_agent['phone']));
    } else {
        fputcsv($output, array('No agent assigned'));
    }
    
    // Output updates
    fputcsv($output, array(''));
    fputcsv($output, array('Status Updates'));
    fputcsv($output, array('Date', 'Status', 'Updated By', 'Notes'));
    
    if ($updates_result->num_rows > 0) {
        while ($update = $updates_result->fetch_assoc()) {
            fputcsv($output, array(
                format_date($update['update_date']),
                $update['status_name'],
                $update['full_name'],
                $update['update_text']
            ));
        }
    } else {
        fputcsv($output, array('No updates'));
    }
    
    // Close the file pointer
    fclose($output);
    exit;
}

// Process form submission for bulk export
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get filter parameters
    $status_filter = isset($_POST['status']) ? sanitize_input($_POST['status']) : '';
    $type_filter = isset($_POST['type']) ? (int)$_POST['type'] : 0;
    $date_from = isset($_POST['date_from']) ? sanitize_input($_POST['date_from']) : '';
    $date_to = isset($_POST['date_to']) ? sanitize_input($_POST['date_to']) : '';
    
    // Build query
    $sql = "SELECT i.incident_id, i.title, i.description, i.location, i.incident_date, i.reported_date, 
                   i.is_anonymous, ct.name as crime_type, sc.name as status, u.full_name as reporter_name, 
                   u.email as reporter_email
            FROM incidents i
            JOIN crime_types ct ON i.crime_type_id = ct.type_id
            JOIN status_codes sc ON i.status_id = sc.status_id
            JOIN users u ON i.user_id = u.user_id
            WHERE 1=1";
    
    $params = array();
    $types = "";
    
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
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        $_SESSION['error'] = "No reports found matching the criteria.";
    } else {
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="crime_reports_' . date('Y-m-d') . '.csv"');
        
        // Create a file pointer connected to the output stream
        $output = fopen('php://output', 'w');
        
        // Output the column headings
        fputcsv($output, array(
            'Report ID', 'Title', 'Crime Type', 'Status', 'Location', 
            'Incident Date', 'Reported Date', 'Reporter', 'Reporter Email', 'Description'
        ));
        
        // Output each row of the data
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, array(
                $row['incident_id'],
                $row['title'],
                $row['crime_type'],
                $row['status'],
                $row['location'],
                format_date($row['incident_date']),
                format_date($row['reported_date']),
                $row['is_anonymous'] ? 'Anonymous' : $row['reporter_name'],
                $row['is_anonymous'] ? 'N/A' : $row['reporter_email'],
                $row['description']
            ));
        }
        
        // Close the file pointer
        fclose($output);
        exit;
    }
}

// Get crime types for filter
$crime_types = get_crime_types($conn);

// Get status codes for filter
$status_codes = get_status_codes($conn);

// Include header
include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Export Reports</h2>
    <a href="dashboard.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
    </a>
</div>

<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Export Reports to CSV</h5>
    </div>
    <div class="card-body">
        <form method="post" action="">
            <div class="row mb-3">
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select name="status" id="status" class="form-select">
                        <option value="">All Status</option>
                        <?php foreach ($status_codes as $status): ?>
                            <option value="<?php echo $status['name']; ?>">
                                <?php echo htmlspecialchars($status['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="type" class="form-label">Crime Type</label>
                    <select name="type" id="type" class="form-select">
                        <option value="0">All Types</option>
                        <?php foreach ($crime_types as $type): ?>
                            <option value="<?php echo $type['type_id']; ?>">
                                <?php echo htmlspecialchars($type['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="date_from" class="form-label">From Date</label>
                    <input type="date" class="form-control" id="date_from" name="date_from">
                </div>
                <div class="col-md-3">
                    <label for="date_to" class="form-label">To Date</label>
                    <input type="date" class="form-control" id="date_to" name="date_to">
                </div>
            </div>
            
            <div class="d-grid">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-file-csv me-2"></i>Export Reports
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Export Options</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-file-csv fa-3x mb-3 text-primary"></i>
                        <h5 class="card-title">All Reports</h5>
                        <p class="card-text">Export all reports in the system without any filters.</p>
                        <a href="export_csv.php" class="btn btn-outline-primary">Export All</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-exclamation-circle fa-3x mb-3 text-warning"></i>
                        <h5 class="card-title">Open Reports</h5>
                        <p class="card-text">Export all reports that are currently open or under investigation.</p>
                        <form method="post" action="">
                            <input type="hidden" name="status" value="Under Investigation">
                            <button type="submit" class="btn btn-outline-warning">Export Open</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-check-circle fa-3x mb-3 text-success"></i>
                        <h5 class="card-title">Closed Reports</h5>
                        <p class="card-text">Export all reports that have been resolved and closed.</p>
                        <form method="post" action="">
                            <input type="hidden" name="status" value="Closed">
                            <button type="submit" class="btn btn-outline-success">Export Closed</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include '../includes/footer.php';
?>
