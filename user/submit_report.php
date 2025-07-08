<?php
/**
 * Submit Report
 * User page for submitting a new crime report
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
    $_SESSION['error'] = "You must be logged in to submit a report.";
    header("Location: ../login.php");
    exit;
}

// Get crime types for dropdown
$crime_types = get_crime_types($conn);

$error = '';
$success = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $title = sanitize_input($_POST['title']);
    $crime_type_id = (int)$_POST['crime_type_id'];
    $location = sanitize_input($_POST['location']);
    $incident_date = sanitize_input($_POST['incident_date']);
    $description = sanitize_input($_POST['description']);
    $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;
    
    // Validate input
    if (empty($title) || empty($location) || empty($incident_date) || empty($description)) {
        $error = "All required fields must be filled out.";
    } else {
        // Get user ID from session
        $user_id = $_SESSION['user_id'];
        
        // Get initial status ID (New)
        $sql = "SELECT status_id FROM status_codes WHERE name = 'New'";
        $result = $conn->query($sql);
        $row = $result->fetch_assoc();
        $status_id = $row['status_id'];
        
        // Insert incident
        $sql = "INSERT INTO incidents (user_id, crime_type_id, status_id, title, description, location, incident_date, is_anonymous) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiissssi", $user_id, $crime_type_id, $status_id, $title, $description, $location, $incident_date, $is_anonymous);
        
        if ($stmt->execute()) {
            $incident_id = $conn->insert_id;
            $success = "Your report has been submitted successfully.";
            
            // Redirect to view the report
            header("Location: view_report.php?id=$incident_id&new=1");
            exit;
        } else {
            $error = "Failed to submit report. Please try again.";
        }
    }
}

// Include header
include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Submit a Report</h2>
    <a href="dashboard.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
    </a>
</div>

<div class="card">
    <div class="card-header bg-secondary text-white">
        <h5 class="mb-0">Report Details</h5>
    </div>
    <div class="card-body">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="post" action="" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label for="title" class="form-label">Incident Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title" required>
                        <div class="invalid-feedback">
                            Please provide a title for the incident.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="crime_type_id" class="form-label">Crime Type <span class="text-danger">*</span></label>
                        <select class="form-select" id="crime_type_id" name="crime_type_id" required>
                            <option value="">Select Crime Type</option>
                            <?php foreach ($crime_types as $type): ?>
                                <option value="<?php echo $type['type_id']; ?>"><?php echo htmlspecialchars($type['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">
                            Please select a crime type.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="location" class="form-label">Location <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="location" name="location" required>
                        <div class="invalid-feedback">
                            Please provide the location of the incident.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="incident_date" class="form-label">Incident Date and Time <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control" id="incident_date" name="incident_date" required>
                        <div class="invalid-feedback">
                            Please provide the date and time of the incident.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="description" name="description" rows="5" required></textarea>
                        <div class="invalid-feedback">
                            Please provide a description of the incident.
                        </div>
                        <small class="text-muted">Please provide as much detail as possible about the incident.</small>
                    </div>
                    
                    <?php if (is_logged_in()): ?>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="is_anonymous" name="is_anonymous">
                            <label class="form-check-label" for="is_anonymous">Submit anonymously (your identity will be hidden from public)</label>
                        </div>
                    <?php endif; ?>
                    
                    <button type="submit" class="btn btn-custom-primary" <?php echo !is_logged_in() ? 'disabled' : ''; ?>>Submit Report</button>
                    
                    <?php if (!is_logged_in()): ?>
                        <a href="login.php" class="btn btn-custom-success ms-2">Login to Submit</a>
                    <?php endif; ?>
                </form>
    </div>
</div>

<?php
// Include footer
include '../includes/footer.php';
?>
