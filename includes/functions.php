<?php
// Function to check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Function to get user role - MOVED TO TOP FOR PRIORITY
function get_user_role() {
    return isset($_SESSION['role']) ? $_SESSION['role'] : null;
}

// Function to check user role
function has_role($role) {
    return is_logged_in() && $_SESSION['role'] == $role;
}

// Function to redirect based on user role
function redirect_by_role() {
    if (has_role('admin')) {
        header('Location: admin/dashboard.php');
    } elseif (has_role('agent')) {
        header('Location: agent/dashboard.php');
    } else {
        header('Location: user/dashboard.php');
    }
    exit;
}

// Function to format date
function format_date($date, $format = 'Y-m-d H:i:s') {
    return date($format, strtotime($date));
}

// Function to sanitize input
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Function to generate a random string
function generate_random_string($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

// Function to check if a file is an image
function is_image($file) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    return in_array($file['type'], $allowed_types);
}

// Function to upload a file
function upload_file($file, $upload_dir, $allowed_types = [], $max_size = 5242880) {
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Check if file was uploaded without errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [
            'success' => false,
            'message' => 'Error uploading file: ' . $file['error']
        ];
    }
    
    // Check file size
    if ($file['size'] > $max_size) {
        return [
            'success' => false,
            'message' => 'File is too large. Maximum size is ' . ($max_size / 1024 / 1024) . 'MB.'
        ];
    }
    
    // Check file type if allowed types are specified
    if (!empty($allowed_types) && !in_array($file['type'], $allowed_types)) {
        return [
            'success' => false,
            'message' => 'Invalid file type. Allowed types: ' . implode(', ', $allowed_types)
        ];
    }
    
    // Generate a unique filename
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $new_filename = generate_random_string() . '.' . $file_extension;
    $upload_path = $upload_dir . '/' . $new_filename;
    
    // Move the uploaded file
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        return [
            'success' => true,
            'filename' => $new_filename,
            'path' => $upload_path
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Failed to move uploaded file.'
        ];
    }
}

// Function to get all crime types from database
function get_crime_types($conn) {
    $types = array();
    $sql = "SELECT * FROM crime_types ORDER BY name";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $types[] = $row;
        }
    }
    
    return $types;
}

// Function to get all status codes from database
function get_status_codes($conn) {
    $statuses = array();
    $sql = "SELECT * FROM status_codes ORDER BY name";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $statuses[] = $row;
        }
    }
    
    return $statuses;
}

// Function to get user by ID
function get_user_by_id($conn, $user_id) {
    $user_id = (int)$user_id;
    $sql = "SELECT * FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

// Function to get incident by ID
function get_incident_by_id($conn, $incident_id) {
    $incident_id = (int)$incident_id;
    $sql = "SELECT i.*, ct.name as crime_type, sc.name as status, u.full_name as reporter_name 
            FROM incidents i
            JOIN crime_types ct ON i.crime_type_id = ct.type_id
            JOIN status_codes sc ON i.status_id = sc.status_id
            JOIN users u ON i.user_id = u.user_id
            WHERE i.incident_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $incident_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

// Function to get assigned agent for an incident
function get_assigned_agent($conn, $incident_id) {
    $incident_id = (int)$incident_id;
    $sql = "SELECT a.*, u.full_name, u.email, u.phone
            FROM incident_agent ia
            JOIN agents a ON ia.agent_id = a.agent_id
            JOIN users u ON a.user_id = u.user_id
            WHERE ia.incident_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $incident_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

// Function to get all agents
function get_all_agents($conn) {
    $agents = array();
    $sql = "SELECT a.*, u.full_name, u.email, u.phone
            FROM agents a
            JOIN users u ON a.user_id = u.user_id
            WHERE a.status = 'active'
            ORDER BY u.full_name";
    
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $agents[] = $row;
        }
    }
    
    return $agents;
}

// Function to count incidents with various filters
function count_incidents($conn, $status = null, $user_id = null, $agent_id = null) {
    $where_clauses = array();
    $params = array();
    $types = "";
    
    if ($status) {
        $where_clauses[] = "sc.name = ?";
        $params[] = $status;
        $types .= "s";
    }
    
    if ($user_id) {
        $where_clauses[] = "i.user_id = ?";
        $params[] = (int)$user_id;
        $types .= "i";
    }
    
    if ($agent_id) {
        $where_clauses[] = "ia.agent_id = ?";
        $types .= "i";
        $params[] = (int)$agent_id;
    }
    
    $where_sql = "";
    if (!empty($where_clauses)) {
        $where_sql = "WHERE " . implode(" AND ", $where_clauses);
    }
    
    $sql = "SELECT COUNT(*) as count FROM incidents i
            JOIN status_codes sc ON i.status_id = sc.status_id";
    
    if ($agent_id) {
        $sql .= " JOIN incident_agent ia ON i.incident_id = ia.incident_id";
    }
    
    $sql .= " $where_sql";
    
    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return (int)$row['count'];
}

// Function to display error message
function display_error($message) {
    return '<div class="alert alert-danger" role="alert">' . $message . '</div>';
}

// Function to display success message
function display_success($message) {
    return '<div class="alert alert-success" role="alert">' . $message . '</div>';
}

// Function to check if email is valid
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Function to hash password
function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Function to verify password
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

// Function to redirect
function redirect($url) {
    header("Location: $url");
    exit;
}
?>
