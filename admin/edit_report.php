<?php
/**
 * Edit Report
 * Admin page for editing a report
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

// Check if ID parameter exists
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Invalid report ID.";
    header("Location: view_reports.php");
    exit;
}

$incident_id = (int)$_GET['id'];

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
    header("Location: view_reports.php");
    exit;
}

$incident = $result->fetch_assoc();

// Get crime types for dropdown
$crime_types = get_crime_types($conn);

// Get status codes for dropdown
$status_codes = get_status_codes($conn);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = sanitize_input($_POST['title']);
    $crime_type_id = (int)$_POST['crime_type_id'];
    $status_id = (int)$_POST['status_id'];
    $location = sanitize_input($_POST['location']);
    $incident_date = sanitize_input($_POST['incident_date']);
    $description = sanitize_input($_POST['description']);
    $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;
    
    // Validate input
    if (empty($title) || empty($location) || empty($incident_date) || empty($description)) {
        $_SESSION['error'] = "All required fields must be filled out.";
    } else {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Update incident
            $sql = "UPDATE incidents SET 
                    crime_type_id = ?, 
                    status_id = ?, 
                    title = ?, 
                    description = ?, 
                    location = ?, 
                    incident_date = ?, 
                    is_anonymous = ? 
                    WHERE incident_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iissssii", $crime_type_id, $status_id, $title, $description, $location, $incident_date, $is_anonymous, $incident_id);
            
            if ($stmt->execute()) {
                // Add status update if status changed
                if ($status_id != $incident['status_id']) {
                    $update_text = "Status updated by admin.";
                    $sql = "INSERT INTO incident_updates (incident_id, user_id, status_id, update_text) VALUES (?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("iiis", $incident_id, $_SESSION['user_id'], $status_id, $update_text);
                    $stmt->execute();
                }
                
                // Commit transaction
                $conn->commit();
                
                $_SESSION['success'] = "Report updated successfully.";
                header("Location: view_reports.php?id=$incident_id");
                exit;
            } else {
                throw new Exception("Failed to update report.");
            }
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $_SESSION['error'] = "Error updating report: " . $e->getMessage();
        }
    }
}

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Edit Report #<?php echo $incident_id; ?></h2>
    <a href="view_reports.php?id=<?php echo $incident_id; ?>" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i>Back to Report
    </a>
</div>

<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Edit Report Details</h5>
    </div>
    <div class="card-body">
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <form method="post" action="">
            <div class="mb-3">
                <label for="title" class="form-label">Incident Title <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($incident['title']); ?>" required>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="crime_type_id" class="form-label">Crime Type <span class="text-danger">*</span></label>
                    <select class="form-select" id="crime_type_id" name="crime_type_id" required>
                        <option value="">Select Crime Type</option>
                        <?php foreach ($crime_types as $type): ?>
                            <option value="<?php echo $type['type_id']; ?>" <?php echo $incident['crime_type_id'] == $type['type_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="status_id" class="form-label">Status <span class="text-danger">*</span></label>
                    <select class="form-select" id="status_id" name="status_id" required>
                        <option value="">Select Status</option>
                        <?php foreach ($status_codes as $status): ?>
                            <option value="<?php echo $status['status_id']; ?>" <?php echo $incident['status_id'] == $status['status_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($status['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="location" class="form-label">Location <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="location" name="location" value="<?php echo htmlspecialchars($incident['location']); ?>" required>
            </div>
            
            <div class="mb-3">
                <label for="incident_date" class="form-label">Incident Date and Time <span class="text-danger">*</span></label>
                <input type="datetime-local" class="form-control" id="incident_date" name="incident_date" value="<?php echo date('Y-m-d\TH:i', strtotime($incident['incident_date'])); ?>" required>
            </div>
            
            <div class="mb-3">
                <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                <textarea class="form-control" id="description" name="description" rows="5" required><?php echo htmlspecialchars($incident['description']); ?></textarea>
                <div class="form-text">Please provide as much detail as possible about the incident.</div>
            </div>
            
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="is_anonymous" name="is_anonymous" <?php echo $incident['is_anonymous'] ? 'checked' : ''; ?>>
                <label class="form-check-label" for="is_anonymous">Submit anonymously (reporter's identity will be hidden from public)</label>
            </div>
            
            <div class="d-grid">
                <button type="submit" class="btn btn-primary">Update Report</button>
            </div>
        </form>
    </div>
</div>

<?php
include '../includes/footer.php';
?>
