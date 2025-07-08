<?php
/**
 * Database Check
 * Diagnostic tool to check database tables and connections
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

// Function to check if a table exists
function table_exists($conn, $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    return $result->num_rows > 0;
}

// Function to count records in a table
function count_records($conn, $table) {
    $result = $conn->query("SELECT COUNT(*) as count FROM $table");
    if ($result) {
        $row = $result->fetch_assoc();
        return $row['count'];
    }
    return 0;
}

// Function to check table structure
function get_table_structure($conn, $table) {
    $result = $conn->query("DESCRIBE $table");
    $columns = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row;
        }
    }
    return $columns;
}

// List of expected tables
$expected_tables = [
    'users',
    'agents',
    'incidents',
    'crime_types',
    'status_codes',
    'incident_updates',
    'incident_agent'
];

// Check each table
$table_status = [];
foreach ($expected_tables as $table) {
    $exists = table_exists($conn, $table);
    $count = $exists ? count_records($conn, $table) : 0;
    $structure = $exists ? get_table_structure($conn, $table) : [];
    
    $table_status[$table] = [
        'exists' => $exists,
        'count' => $count,
        'structure' => $structure
    ];
}

// Test specific queries
$query_tests = [
    'users' => "SELECT * FROM users LIMIT 5",
    'agents' => "SELECT a.*, u.username, u.full_name FROM agents a JOIN users u ON a.user_id = u.user_id LIMIT 5",
    'incidents' => "SELECT i.*, ct.name as crime_type, sc.name as status, u.full_name as reporter_name 
                   FROM incidents i
                   JOIN crime_types ct ON i.crime_type_id = ct.type_id
                   JOIN status_codes sc ON i.status_id = sc.status_id
                   JOIN users u ON i.user_id = u.user_id
                   LIMIT 5"
];

$query_results = [];
foreach ($query_tests as $name => $query) {
    try {
        $result = $conn->query($query);
        $success = ($result !== false);
        $error = $success ? null : $conn->error;
        $rows = [];
        
        if ($success && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        
        $query_results[$name] = [
            'success' => $success,
            'error' => $error,
            'count' => $success ? $result->num_rows : 0,
            'rows' => $rows
        ];
    } catch (Exception $e) {
        $query_results[$name] = [
            'success' => false,
            'error' => $e->getMessage(),
            'count' => 0,
            'rows' => []
        ];
    }
}

// Include header
include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Database Diagnostic</h2>
    <a href="dashboard.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
    </a>
</div>

<!-- Database Connection -->
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Database Connection</h5>
    </div>
    <div class="card-body">
        <p><strong>Connection Status:</strong> <?php echo $conn->connect_error ? 'Failed: ' . $conn->connect_error : 'Connected'; ?></p>
        <p><strong>Server Info:</strong> <?php echo $conn->server_info; ?></p>
        <p><strong>Character Set:</strong> <?php echo $conn->character_set_name(); ?></p>
    </div>
</div>

<!-- Table Status -->
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Table Status</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Table Name</th>
                        <th>Exists</th>
                        <th>Record Count</th>
                        <th>Structure</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($table_status as $table => $status): ?>
                        <tr>
                            <td><?php echo $table; ?></td>
                            <td>
                                <?php if ($status['exists']): ?>
                                    <span class="badge bg-success">Yes</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">No</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $status['count']; ?></td>
                            <td>
                                <?php if (!empty($status['structure'])): ?>
                                    <button class="btn btn-sm btn-info" type="button" data-bs-toggle="collapse" data-bs-target="#structure-<?php echo $table; ?>">
                                        Show Structure
                                    </button>
                                    <div class="collapse mt-2" id="structure-<?php echo $table; ?>">
                                        <div class="card card-body">
                                            <pre><?php print_r($status['structure']); ?></pre>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">Not available</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Query Tests -->
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Query Tests</h5>
    </div>
    <div class="card-body">
        <?php foreach ($query_tests as $name => $query): ?>
            <div class="mb-4">
                <h5><?php echo ucfirst($name); ?> Query</h5>
                <div class="bg-light p-2 mb-2">
                    <code><?php echo htmlspecialchars($query); ?></code>
                </div>
                
                <?php $result = $query_results[$name]; ?>
                
                <?php if ($result['success']): ?>
                    <div class="alert alert-success">
                        Query executed successfully. Returned <?php echo $result['count']; ?> rows.
                    </div>
                    
                    <?php if ($result['count'] > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <?php foreach (array_keys($result['rows'][0]) as $column): ?>
                                            <th><?php echo htmlspecialchars($column); ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($result['rows'] as $row): ?>
                                        <tr>
                                            <?php foreach ($row as $value): ?>
                                                <td><?php echo htmlspecialchars($value); ?></td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            Query returned no rows. The table might be empty.
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-danger">
                        Query failed: <?php echo htmlspecialchars($result['error']); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Recommendations -->
<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Recommendations</h5>
    </div>
    <div class="card-body">
        <ul>
            <?php
            $has_issues = false;
            
            // Check for missing tables
            foreach ($table_status as $table => $status) {
                if (!$status['exists']) {
                    $has_issues = true;
                    echo "<li class='text-danger'>Table '$table' is missing. Please run the database setup script.</li>";
                }
            }
            
            // Check for empty tables
            foreach ($table_status as $table => $status) {
                if ($status['exists'] && $status['count'] == 0) {
                    $has_issues = true;
                    echo "<li class='text-warning'>Table '$table' exists but contains no records. You may need to add sample data.</li>";
                }
            }
            
            // Check for query failures
            foreach ($query_results as $name => $result) {
                if (!$result['success']) {
                    $has_issues = true;
                    echo "<li class='text-danger'>The $name query failed. Check the table structure and relationships.</li>";
                }
            }
            
            if (!$has_issues) {
                echo "<li class='text-success'>No major database issues detected. If you're still experiencing problems, check your PHP code for logical errors.</li>";
            }
            ?>
            
            <li>Make sure your database user has sufficient permissions (SELECT, INSERT, UPDATE, DELETE) on all tables.</li>
            <li>Check that the database schema matches what your code expects. Column names and types should match.</li>
            <li>Ensure that foreign key relationships are correctly set up between tables.</li>
            <li>If tables are empty, add sample data to test functionality.</li>
        </ul>
        
        <div class="mt-3">
            <h6>Common Solutions:</h6>
            <ol>
                <li>Re-run the database setup script to recreate tables and sample data.</li>
                <li>Check for SQL syntax errors in your queries.</li>
                <li>Verify that all required tables and columns exist.</li>
                <li>Ensure that the database user has sufficient permissions.</li>
                <li>Check for foreign key constraint violations.</li>
            </ol>
        </div>
    </div>
</div>

<?php
// Include footer
include '../includes/footer.php';
?>
