<?php if (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || 
              strpos($_SERVER['PHP_SELF'], '/agent/') !== false || 
              strpos($_SERVER['PHP_SELF'], '/user/') !== false): ?>
        </div> <!-- End of container-fluid -->
        </div> <!-- End of main-content -->
        </div> <!-- End of dashboard-container -->
    <?php else: ?>
        </div> <!-- End of container -->
        
        <!-- Footer for public pages -->
        <footer class="bg-dark text-white py-4 mt-5">
            <div class="container">
                <div class="row">
                    <div class="col-md-6">
                        <h5>Crime Reporting System</h5>
                        <p>A secure platform for reporting and managing crime incidents.</p>
                    </div>
                    <div class="col-md-3">
                        <h5>Quick Links</h5>
                        <ul class="list-unstyled">
                            <li><a href="index.php" class="text-white">Home</a></li>
                            <li><a href="report.php" class="text-white">Report Crime</a></li>
                            <li><a href="login.php" class="text-white">Login</a></li>
                            <li><a href="register.php" class="text-white">Register</a></li>
                        </ul>
                    </div>
                    <div class="col-md-3">
                        <h5>Contact</h5>
                        <address>
                            <i class="fas fa-map-marker-alt me-2"></i>Sardauna estate, Katsina<br>
                            <i class="fas fa-phone me-2"></i>+(234) 803 369 8607 <br>
                            <i class="fas fa-envelope me-2"></i> info@crimereport.com
                        </address>
                    </div>
                </div>
                <hr>
                <div class="text-center">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> Crime Reporting System. All rights reserved.</p>
                </div>
            </div>
        </footer>
    <?php endif; ?>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery (required for some Bootstrap components) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script src="<?php echo isset($base_path) ? $base_path : ''; ?>assets/js/script.js"></script>
    
    <?php if (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || 
              strpos($_SERVER['PHP_SELF'], '/agent/') !== false || 
              strpos($_SERVER['PHP_SELF'], '/user/') !== false): ?>
    <!-- Dashboard specific JavaScript -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Sidebar toggle functionality
        const sidebarToggle = document.getElementById('sidebarToggle');
        const dashboardContainer = document.querySelector('.dashboard-container');
        
        if (sidebarToggle && dashboardContainer) {
            sidebarToggle.addEventListener('click', function() {
                dashboardContainer.classList.toggle('sidebar-collapsed');
                
                // Change the icon based on the sidebar state
                const icon = this.querySelector('i');
                if (dashboardContainer.classList.contains('sidebar-collapsed')) {
                    icon.classList.remove('fa-chevron-left');
                    icon.classList.add('fa-chevron-right');
                } else {
                    icon.classList.remove('fa-chevron-right');
                    icon.classList.add('fa-chevron-left');
                }
            });
        }
    });
    </script>
    <?php endif; ?>
</body>
</html>
