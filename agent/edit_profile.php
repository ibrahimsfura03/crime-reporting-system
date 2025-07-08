<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role('agent')) {
    $_SESSION['error'] = "You must be logged in as an agent to access this page.";
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

// Get agent information
$sql = "SELECT * FROM agents WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $_SESSION['error'] = "Agent profile not found.";
    header("Location: ../logout.php");
    exit;
}

$agent = $result->fetch_assoc();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = sanitize_input($_POST['full_name']);
    $email = sanitize_input($_POST['email']);
    $phone = sanitize_input($_POST['phone']);
    $address = sanitize_input($_POST['address']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($full_name) || empty($email)) {
        $error = "Name and email are required.";
    } elseif (!is_valid_email($email)) {
        $error = "Invalid email format.";
    } else {
        // Check if email exists for another user
        $sql = "SELECT user_id FROM users WHERE email = ? AND user_id != ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $email, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "Email already exists. Please use a different one.";
        } else {
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // Update user profile
                $sql = "UPDATE users SET full_name = ?, email = ?, phone = ?, address = ? WHERE user_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssi", $full_name, $email, $phone, $address, $user_id);
                $stmt->execute();
                
                // Handle profile image upload
                if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
                    $allowed = array('jpg', 'jpeg', 'png', 'gif');
                    $filename = $_FILES['profile_image']['name'];
                    $ext = pathinfo($filename, PATHINFO_EXTENSION);
                    
                    if (in_array(strtolower($ext), $allowed)) {
                        // Create uploads directory if it doesn't exist
                        $upload_dir = '../uploads/profile/';
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        
                        // Generate unique filename
                        $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $ext;
                        $destination = $upload_dir . $new_filename;
                        
                        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $destination)) {
                            // Update profile image in database
                            $sql = "UPDATE users SET profile_image = ? WHERE user_id = ?";
                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param("si", $new_filename, $user_id);
                            $stmt->execute();
                        } else {
                            throw new Exception("Failed to upload profile image.");
                        }
                    } else {
                        throw new Exception("Invalid file type. Only JPG, JPEG, PNG and GIF are allowed.");
                    }
                }
                
                // Update password if provided
                if (!empty($current_password) && !empty($new_password)) {
                    // Verify current password
                    if (!verify_password($current_password, $user['password'])) {
                        throw new Exception("Current password is incorrect.");
                    }
                    
                    // Check if new password matches confirmation
                    if ($new_password != $confirm_password) {
                        throw new Exception("New passwords do not match.");
                    }
                    
                    // Check password length
                    if (strlen($new_password) < 6) {
                        throw new Exception("New password must be at least 6 characters long.");
                    }
                    
                    // Hash and update password
                    $hashed_password = hash_password($new_password);
                    $sql = "UPDATE users SET password = ? WHERE user_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("si", $hashed_password, $user_id);
                    $stmt->execute();
                }
                
                // Commit transaction
                $conn->commit();
                
                // Update session data
                $_SESSION['full_name'] = $full_name;
                
                $success = "Profile updated successfully.";
                
                // Refresh user data
                $user = get_user_by_id($conn, $user_id);
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                $error = $e->getMessage();
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-custom-primary text-white">
                <h4 class="mb-0">Edit Agent Profile</h4>
            </div>
            <div class="card-body">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <form method="post" action="" class="needs-validation" novalidate enctype="multipart/form-data">
                    <div class="row mb-4">
                        <div class="col-md-12 text-center">
                            <div class="mb-3">
                                <img src="<?php echo $base_url; ?>uploads/profile/<?php echo !empty($user['profile_image']) ? $user['profile_image'] : 'default.jpg'; ?>" class="rounded-circle img-thumbnail" style="width: 150px; height: 150px; object-fit: cover;" alt="Profile Image">
                            </div>
                            <div class="mb-3">
                                <label for="profile_image" class="form-label">Change Profile Image</label>
                                <input type="file" class="form-control" id="profile_image" name="profile_image">
                                <small class="text-muted">Allowed formats: JPG, JPEG, PNG, GIF. Max size: 2MB</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                            <small class="text-muted">Username cannot be changed.</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="badge_number" class="form-label">Badge Number</label>
                            <input type="text" class="form-control" id="badge_number" value="<?php echo htmlspecialchars($agent['badge_number']); ?>" disabled>
                            <small class="text-muted">Badge number cannot be changed.</small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                        <div class="invalid-feedback">
                            Please enter your full name.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        <div class="invalid-feedback">
                            Please provide a valid email.
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="1"><?php echo htmlspecialchars($user['address']); ?></textarea>
                        </div>
                    </div>
                    
                    <hr>
                    <h5>Change Password</h5>
                    <p class="text-muted">Leave blank if you don't want to change your password.</p>
                    
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password">
                            <small class="text-muted">Password must be at least 6 characters long.</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-custom-primary">Update Profile</button>
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
include '../includes/footer.php';
?>
