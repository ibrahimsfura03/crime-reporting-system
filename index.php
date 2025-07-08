<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';
include 'includes/header.php';
?>

<section class="hero-section">
    <div class="container hero-content">
        <div class="row justify-content-center">
            <div class="col-md-8 text-center hero-overlay">
                <h1 class="display-4">Crime Reporting System</h1>
                <p class="lead">Report and track incidents in your community</p>
                <div class="mt-4">
                    <a href="report.php" class="btn btn-custom-accent btn-lg me-2">Report a Crime Now</a>
                    <?php if (is_logged_in()): ?>
                        <?php if (has_role('admin')): ?>
                            <a href="admin/view_reports.php" class="btn btn-outline-light btn-lg">View Reports</a>
                        <?php elseif (has_role('agent')): ?>
                            <a href="agent/view_assigned.php" class="btn btn-outline-light btn-lg">View Reports</a>
                        <?php else: ?>
                            <a href="user/view_my_reports.php" class="btn btn-outline-light btn-lg">View Reports</a>
                        <?php endif; ?>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-outline-light btn-lg">View Reports</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <h2 class="text-center mb-4">Resolved Cases</h2>
        <div class="row">
            <?php
            $sql = "SELECT i.incident_id, i.title, i.description, i.incident_date, ct.name as crime_type 
                    FROM incidents i
                    JOIN status_codes sc ON i.status_id = sc.status_id
                    JOIN crime_types ct ON i.crime_type_id = ct.type_id
                    WHERE sc.name = 'Closed'
                    ORDER BY i.incident_date DESC
                    LIMIT 3";
            
            $result = $conn->query($sql);
            
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    ?>
                    <div class="col-md-4 mb-4 py-5">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($row['title']); ?></h5>
                                <h6 class="card-subtitle mb-2 text-muted"><?php echo htmlspecialchars($row['crime_type']); ?></h6>
                                <p class="card-text"><?php echo substr(htmlspecialchars($row['description']), 0, 100) . '...'; ?></p>
                                <p class="text-muted">Date: <?php echo format_date($row['incident_date'], 'M d, Y'); ?></p>
                                <?php if (is_logged_in()): ?>
                                    <a href="user/view_report.php?id=<?php echo $row['incident_id']; ?>" class="btn btn-custom-primary btn-sm">Read More</a>
                                <?php else: ?>
                                    <a href="login.php" class="btn btn-custom-primary btn-sm">Login to Report too</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php
                }
            } else {
                echo '<div class="col-12 text-center"><p>No resolved cases to display.</p></div>';
            }
            ?>
        </div>
    </div>
</section>

<section class="py-5 bg-light">
    <div class="container">
        <h2 class="text-center mb-4">Crime Statistics</h2>
        <div class="row text-center">
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <i class="fas fa-file-alt fa-3x mb-3 text-primary"></i>
                        <h5 class="card-title">Total Reports</h5>
                        <?php
                        $sql = "SELECT COUNT(*) as count FROM incidents";
                        $result = $conn->query($sql);
                        $row = $result->fetch_assoc();
                        ?>
                        <p class="card-text display-4"><?php echo $row['count']; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <i class="fas fa-search fa-3x mb-3 text-warning"></i>
                        <h5 class="card-title">Under Investigation</h5>
                        <?php
                        $sql = "SELECT COUNT(*) as count FROM incidents i
                                JOIN status_codes sc ON i.status_id = sc.status_id
                                WHERE sc.name = 'Under Investigation'";
                        $result = $conn->query($sql);
                        $row = $result->fetch_assoc();
                        ?>
                        <p class="card-text display-4"><?php echo $row['count']; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <i class="fas fa-check-circle fa-3x mb-3 text-success"></i>
                        <h5 class="card-title">Resolved Cases</h5>
                        <?php
                        $sql = "SELECT COUNT(*) as count FROM incidents i
                                JOIN status_codes sc ON i.status_id = sc.status_id
                                WHERE sc.name = 'Closed'";
                        $result = $conn->query($sql);
                        $row = $result->fetch_assoc();
                        ?>
                        <p class="card-text display-4"><?php echo $row['count']; ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <h2 class="text-center mb-4">How It Works</h2>
        <div class="row">
            <div class="col-md-4 mb-4 text-center">
                <div class="p-3">
                    <i class="fas fa-user-plus fa-4x mb-3 text-primary"></i>
                    <h4>1. Register an Account</h4>
                    <p>Create an account to access all features of the Crime Reporting System.</p>
                </div>
            </div>
            <div class="col-md-4 mb-4 text-center">
                <div class="p-3">
                    <i class="fas fa-file-alt fa-4x mb-3 text-primary"></i>
                    <h4>2. Submit a Report</h4>
                    <p>Fill out the crime report form with all relevant details about the incident.</p>
                </div>
            </div>
            <div class="col-md-4 mb-4 text-center">
                <div class="p-3">
                    <i class="fas fa-search fa-4x mb-3 text-primary"></i>
                    <h4>3. Track Progress</h4>
                    <p>Monitor the status of your report as it's processed by law enforcement.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
include 'includes/footer.php';
?>
