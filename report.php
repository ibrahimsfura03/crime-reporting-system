<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$crime_types = get_crime_types($conn);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!is_logged_in()) {
        $_SESSION['error'] = "Please login or register to submit a report.";
        header("Location: login.php");
        exit;
    }
    
    $title = sanitize_input($_POST['title']);
    $crime_type_id = (int)$_POST['crime_type_id'];
    $location = sanitize_input($_POST['location']);
    $incident_date = sanitize_input($_POST['incident_date']);
    $description = sanitize_input($_POST['description']);
    $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;
    
    if (empty($title) || empty($location) || empty($incident_date) || empty($description)) {
        $error = "All required fields must be filled out.";
    } else {
        $user_id = $_SESSION['user_id'];
        
        $sql = "SELECT status_id FROM status_codes WHERE name = 'New'";
        $result = $conn->query($sql);
        $row = $result->fetch_assoc();
        $status_id = $row['status_id'];
        
        $sql = "INSERT INTO incidents (user_id, crime_type_id, status_id, title, description, location, incident_date, is_anonymous) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiissssi", $user_id, $crime_type_id, $status_id, $title, $description, $location, $incident_date, $is_anonymous);
        
        if ($stmt->execute()) {
            $success = "Your report has been submitted successfully. You can track its status in your dashboard.";
        } else {
            $error = "Failed to submit report. Please try again.";
        }
    }
}

include 'includes/header.php';
?>

<div class="row justify-content-center mt-5">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-custom-primary text-white">
                <h4 class="mb-0">Report a Crime</h4>
            </div>
            <div class="card-body">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php if (!is_logged_in()): ?>
                    <div class="alert alert-warning">
                        <p>You need to <a href="login.php">login</a> or <a href="register.php">register</a> to submit a report.</p>
                        <p>However, you can still fill out the form below and login/register afterward.</p>
                    </div>
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
    </div>
</div>

<script>
(function () {
    'use strict'
    
    var forms = document.querySelectorAll('.needs-validation')
    
    Array.prototype.slice.call(forms)
        .forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }
                
                form.classList.add('was-validated')
            }, false)
        })
})()
</script>

<?php
include 'includes/footer.php';
?>
